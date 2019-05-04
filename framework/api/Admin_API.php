<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Admin_API extends WP_REST_Controller{
    protected $base = 'admin';


    protected static $instance;

    protected static $NAMESPACE = 'qji';

    public static function get_instance() {

        if ( ! isset( self::$instance ) ) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct(){
        $this->register_routes();
    }

    public function register_routes(){
        register_rest_route( self::$NAMESPACE, '/import', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'import_product' ),
                'permission_callback' => array( $this, 'import_product_permissions_check' )
            )
        ) );

        register_rest_route( self::$NAMESPACE, '/status', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'check_status' ),
                'permission_callback' => array( $this, 'check_status_permissions_check' )
            )
        ) );
    }

    public function check_status(WP_REST_Request $request){

        $order_id = $request->get_param('order_number');

        /**
         * Get order notes
         */

        remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
        $comments = get_comments( array(
            'post_id' => $order_id,
            'orderby' => 'comment_ID',
            'order'   => 'DESC',
            'approve' => 'approve',
            'type'    => 'order_note',
        ) );
        $notes = wp_list_pluck( $comments, 'comment_content' );
        add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );

        $qjc_order_id = false;
        foreach($notes as $note){
            if(stripos($note, 'Order Created')){
                $qjc_order_id = substr($note, stripos($note, 'QJC'));
                $qjc_order_id = explode('</ordernumber>', $qjc_order_id)[0];
            }
        }

        if($qjc_order_id){
            $url = 'http://www.quintessencejewelry.com/index.php/qjcapis/getOrders.xml';

            // TODO update api creds and URL to use from customizer
            $data = array(
                "order_number"      =>  $qjc_order_id,
                "uname"             =>  "mybziscool@gmail.com",
                "pass"              =>  "5080fa692abd9e30be2c16872e3fdc60"
            );

            $curl = curl_init();
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            try{
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                $result = curl_exec($curl);
                if($result === false){
                    return curl_error($curl);
                }else{
                    $p = xml_parser_create();
                    xml_parse_into_struct($p, $result, $values, $index);
                    xml_parser_free($p);
                    $status = $values[3]['attributes']['ORDER_STATUS'];
                    if($status == "Order under process"){
                        return "Order is still being processed.";
                    }else{
                        return $status;
                    }


                }
            }catch(Exception $e){
                return "Unable to automatically retrieve order information, please contact us directly for assistance.";
            }
        }else{
            return "No matching order found, please contact us for assistance.";
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return string
     *
     * Import products to Woocommerce
     */
    public function import_product(WP_REST_Request $request){
        /**
         * Price multiplier set in Customizer
         */
        $multiplier = floatval(get_option('qji_plugin_setting_price_multiplier', 2));

        $product = $request->get_body_params();

        /**
         * Only want to do anything if the product is in stock
         */
        if(intval($product['Qty']) > 0){
            $quantity = $product['Qty'];
            $sku = $product['Sku'];
            $id = wc_get_product_id_by_sku($sku);
            if($id == 0){
                $price = floatval(str_replace(array('$', ','), '', $product['Price'])) * $multiplier;
                $category = $product['Category'];
                $title = $product['Product_Title'];

                $post = array(
                    'post_status' => "publish",
                    'post_title' => $title,
                    'post_type' => "product"
                );
                $post_id = wp_insert_post( $post );

                /**
                 * Handle product attributes
                 */
                $atts = array(
                    'diamond-weight'    =>  $product['Dia_wt__(Carat)'],
                    'gem-weight'        =>  $product['Gem_wt__(Carat)'],
                    'gemstone-detail'   =>  explode(':', $product['Gemstone_detail'])[0],
                    'gross-weight'      =>  $product['Gross_wt__(gms)'],
                    'metal'             =>  $product['Metal'],
                    'metal-weight'      =>  $product['Metal_wt__(gms)'],
                    'setting'           =>  $product['Setting']
                );

                $variation_data = false;
                if($product['Size'] !== '-'){
                    /**
                     * Product has a size and this will be used for variations
                     */
                    wp_set_object_terms($post_id, 'variable', 'product_type');

                    $atts['size'] = substr($product['Size'], 5);
                    $variation_data =  array(
                        'attributes' => array(
                            'size'      => substr($product['Size'], 5),
                        ),
                        'stock_qty'     => $quantity,
                        'sku'       =>  '',
                        'sale_price'     =>  '',
                        'regular_price' =>  $price,
                    );
                }else{
                    /**
                     * Product doesn't have size (like earrings)
                     * Update stock qty and manage accordingly
                     */
                    wp_set_object_terms($post_id, 'simple', 'product_type');
                    update_post_meta( $post_id, '_manage_stock', "yes" );
                    update_post_meta( $post_id, '_stock', $quantity);
                }

                wp_set_object_terms( $post_id, $category, 'product_cat', true);
                update_post_meta( $post_id, '_price', $price );
                update_post_meta( $post_id, '_regular_price', $price );
                update_post_meta($post_id, '_sku', $sku);
                update_post_meta( $post_id, '_stock_status', 'instock');
                update_post_meta( $post_id, 'total_sales', '0');


                $i = 0;

                $product_attributes = array();
                foreach($atts as $key => $value){
                    switch(true){
                        case ($value == "n.a"):
                            break;
                        case ($key == "size"):
                            // Set size term but we aren't setting a specific one, that will be a variation
                            wp_set_object_terms($post_id, $value, 'pa_' . $key, true);
                            $product_attributes['pa_' . $key] = array (
                                'name' => 'pa_' . $key, // set attribute name
                                'value' => $value, // set attribute value
                                'position' => $i,
                                'is_visible' => 1,
                                'is_variation' => 1,
                                'is_taxonomy' => 1
                            );

                            break;
                        case ($key == "gemstone-detail"):
                            // set the product attribute first
                            wp_set_object_terms($post_id, $value, 'pa_' . $key, true);
                            $product_attributes['pa_' . $key] = array (
                                'name' => 'pa_' . $key, // set attribute name
                                'value' => trim($value), // set attribute value
                                'position' => $i,
                                'is_visible' => 1,
                                'is_variation' => 0,
                                'is_taxonomy' => 1
                            );

                            // now the category
                            $parent_id = get_term_by('slug', 'gemstone-detail', 'product_cat')->term_id;
                            $maybe_term = get_term_by("name", trim($value), "product_cat"); //returns either the term or false

                            if($maybe_term){
                                // Term exists already, set it on product
                                wp_set_object_terms($post_id, $maybe_term->term_id, 'product_cat', true);
                            }else{
                                // Term doesn't exist yet
                                $new_term = wp_insert_term(
                                    trim($value), // category name
                                    'product_cat', // taxonomy
                                    array(
                                        'description' => trim($value) . ' Category', // optional
                                        'slug' => str_replace(' ', '-', strtolower(trim($value))), // optional
                                        'parent' => $parent_id, // set it as a sub-category
                                    )
                                );
                                wp_set_object_terms($id, $new_term['term_id'], 'product_cat', true);
                            }
                            break;
                        case ($key == "metal"):
                            // set the product attribute first
                            wp_set_object_terms($post_id, $value, 'pa_' . $key, true);
                            $product_attributes['pa_' . $key] = array (
                                'name' => 'pa_' . $key, // set attribute name
                                'value' => trim($value), // set attribute value
                                'position' => $i,
                                'is_visible' => 1,
                                'is_variation' => 0,
                                'is_taxonomy' => 1
                            );

                            // now the category
                            $parent_id = get_term_by('slug', 'metal', 'product_cat')->term_id;
                            $maybe_term = get_term_by("name", trim($value), "product_cat"); //returns either the term or false

                            if($maybe_term){
                                // Term exists already, set it on product
                                wp_set_object_terms($post_id, $maybe_term->term_id, 'product_cat', true);
                            }else{
                                // Term doesn't exist yet
                                $new_term = wp_insert_term(
                                    trim($value), // category name
                                    'product_cat', // taxonomy
                                    array(
                                        'description' => trim($value) . ' Category', // optional
                                        'slug' => str_replace(' ', '-', strtolower(trim($value))), // optional
                                        'parent' => $parent_id, // set it as a sub-category
                                    )
                                );
                                wp_set_object_terms($id, $new_term['term_id'], 'product_cat', true);
                            }
                            break;
                        case ($key == "setting"):
                            // set the product attribute first
                            wp_set_object_terms($post_id, $value, 'pa_' . $key, true);
                            $product_attributes['pa_' . $key] = array (
                                'name' => 'pa_' . $key, // set attribute name
                                'value' => trim($value), // set attribute value
                                'position' => $i,
                                'is_visible' => 1,
                                'is_variation' => 0,
                                'is_taxonomy' => 1
                            );

                            // now the category
                            $parent_id = get_term_by('slug', 'setting', 'product_cat')->term_id;
                            $maybe_term = get_term_by("name", trim($value), "product_cat"); //returns either the term or false

                            if($maybe_term){
                                // Term exists already, set it on product
                                wp_set_object_terms($post_id, $maybe_term->term_id, 'product_cat', true);
                            }else{
                                // Term doesn't exist yet
                                $new_term = wp_insert_term(
                                    trim($value), // category name
                                    'product_cat', // taxonomy
                                    array(
                                        'description' => trim($value) . ' Category', // optional
                                        'slug' => str_replace(' ', '-', strtolower(trim($value))), // optional
                                        'parent' => $parent_id, // set it as a sub-category
                                    )
                                );
                                wp_set_object_terms($id, $new_term['term_id'], 'product_cat', true);
                            }
                            break;
                        default:
                            wp_set_object_terms($post_id, $value, 'pa_' . $key, true);
                            $product_attributes['pa_' . $key] = array (
                                'name' => 'pa_' . $key, // set attribute name
                                'value' => trim($value), // set attribute value
                                'position' => $i,
                                'is_visible' => 1,
                                'is_variation' => 0,
                                'is_taxonomy' => 1
                            );
                    }
                    $i++;
                }

                update_post_meta($post_id, '_product_attributes', $product_attributes);

                /**
                 * Upload and set Gallery Image
                 */
                $gallery_image_url = $product['GalleryImage_Path'];
                $gallery_attach_id = $this->insert_attachment_from_url($gallery_image_url);

                set_post_thumbnail($post_id, $gallery_attach_id);

                /**
                 * Additional images
                 */
                $images_urls = array(
                    'image1'    =>  isset($product['Image-2_Path']) ? $product['Image-2_Path'] : false,
                    'image2'    =>  isset($product['Image-3_Path']) ? $product['Image-3_Path'] : false,
                    'image3'    =>  isset($product['Image-4_Path']) ? $product['Image-4_Path'] : false
                );

                $attach_ids = array();
                foreach($images_urls as $url){
                    if($url !== false){
                        $attach_id = $this->insert_attachment_from_url($url);
                        $attach_ids[] = $attach_id;
                    }
                }

                add_post_meta($post_id, '_product_image_gallery', implode(',', $attach_ids));

                /**
                 * Create product variation based on size
                 */
                if($variation_data){

                    $this->create_product_variation($post_id, $variation_data);
                }

                return 'Added new product ' . $post_id . ' with a price of ' . $price;
            }else{
                $p = wc_get_product($id);
                if($p->get_type() == 'variable'){
                    $size = substr($product['Size'], 5);
                    $variations = $p->get_available_variations();
                    $present = false;
                    foreach($variations as $v){
                        if($v['attributes']['attribute_pa_size'] == str_replace('.', '-', $size)){
                            $present = true;
                        }
                    }
                    if(!$present){
                        // Add new variation
                        $price = floatval(str_replace(array('$', ','), '', $product['Price'])) * $multiplier;
                        $variation_data =  array(
                            'attributes' => array(
                                'size'      => $size,
                            ),
                            'stock_qty'     => $quantity,
                            'sku'       =>  '',
                            'sale_price'     =>  '',
                            'regular_price' =>  $price,
                        );
                        $this->create_product_variation($id, $variation_data);

                        return 'Added size ' . $size . ' for product ' . $id;
                    }else{
                        return 'Skipped product variation ' . $id . ' because it already exists';
                    }
                }else{
                    return 'Skipped product ' . $id . ' because it already exists';
                }



            }


        }else{
            /**
             * Product quantity of 0 in the sheet so we need to update accordingly
             */

            return 'Skipping product with quantity of 0';

        }
    }

    /**
     * Create a product variation for a defined variable product ID.
     *
     * @since 3.0.0
     * @param int $product_id | Post ID of the product parent variable product.
     * @param array $variation_data | The data to insert in the product.
     * @throws WC_Data_Exception
     */

    function create_product_variation( $product_id, $variation_data ){
        // Get the Variable product object (parent)
        $product = wc_get_product($product_id);

        $variation_post = array(
            'post_title'  => $product->get_title(),
            'post_name'   => 'product-'.$product_id.'-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type'   => 'product_variation',
            'guid'        => $product->get_permalink()
        );

        // Creating the product variation
        $variation_id = wp_insert_post( $variation_post );

        // Get an instance of the WC_Product_Variation object
        $variation = new WC_Product_Variation( $variation_id );

        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute => $term_name )
        {
            $taxonomy = 'pa_'.$attribute; // The attribute taxonomy

            // Check if the Term name exist and if not we create it.
            if( ! term_exists( $term_name, $taxonomy ) )
                wp_insert_term( $term_name, $taxonomy ); // Create the term

            $term_slug = get_term_by('name', $term_name, $taxonomy )->slug; // Get the term slug

            // Get the post Terms names from the parent variable product.
            $post_term_names =  wp_get_post_terms( $product_id, $taxonomy, array('fields' => 'names') );

            // Check if the post term exist and if not we set it in the parent variable product.
            if( ! in_array( $term_name, $post_term_names ) )
                wp_set_post_terms( $product_id, $term_name, $taxonomy, true );

            // Set/save the attribute data in the product variation
            update_post_meta( $variation_id, 'attribute_'.$taxonomy, $term_slug );

        }

        ## Set/save all other data

        // SKU
        if( ! empty( $variation_data['sku'] ) )
            $variation->set_sku( $variation_data['sku'] );

        // Prices
        if( empty( $variation_data['sale_price'] ) ){
            $variation->set_price( $variation_data['regular_price'] );
        } else {
            $variation->set_price( $variation_data['sale_price'] );
            $variation->set_sale_price( $variation_data['sale_price'] );
        }
        $variation->set_regular_price( $variation_data['regular_price'] );

        // Stock
        if( ! empty($variation_data['stock_qty']) ){
            $variation->set_stock_quantity( $variation_data['stock_qty'] );
            $variation->set_manage_stock(true);
            $variation->set_stock_status('');
        } else {
            $variation->set_manage_stock(false);
        }

        $variation->set_weight(''); // weight (resetting)

        $variation->save(); // Save the data
    }


    /**
     * @param $url string link to external image
     * @param null $post_id int post ID
     * @return bool|int|WP_Error
     *
     * Insert a WP attachment from an external URL
     */
    public function insert_attachment_from_url($url, $post_id = null) {

        if( !class_exists( 'WP_Http' ) )
            include_once( ABSPATH . WPINC . '/class-http.php' );

        $http = new WP_Http();
        $response = $http->request( $url );
        if( $response['response']['code'] != 200 ) {
            return false;
        }

        $upload = wp_upload_bits( basename($url), null, $response['body'] );
        if( !empty( $upload['error'] ) ) {
            return false;
        }

        $file_path = $upload['file'];
        $file_name = basename( $file_path );
        $file_type = wp_check_filetype( $file_name, null );
        $attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
        $wp_upload_dir = wp_upload_dir();

        $post_info = array(
            'guid'				=> $wp_upload_dir['url'] . '/' . $file_name,
            'post_mime_type'	=> $file_type['type'],
            'post_title'		=> $attachment_title,
            'post_content'		=> '',
            'post_status'		=> 'inherit',
        );

        // Create the attachment
        $attach_id = wp_insert_attachment( $post_info, $file_path, $post_id );

        // Include image.php
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        // Define attachment metadata
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

        // Assign metadata to attachment
        wp_update_attachment_metadata( $attach_id,  $attach_data );

        return $attach_id;

    }

    public function import_product_permissions_check(){
        // TODO
        return true;
    }

    public function check_status_permissions_check(){
        // TODO
        return true;
    }


} Admin_API::get_instance();
