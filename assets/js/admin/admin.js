const controller_url = QJI_ADMIN_ARGS.API_BASE;
console.log(controller_url);

/**
 * Scripts for handling XLSX import.
 *
 * TODO register and enqueue these scripts  in the plugin driver
 */
output = function(text){
    let outputBox = jQuery('#output');
    let val = outputBox.val();
    outputBox.val(val + '\n' + text);
    outputBox.scrollTop(outputBox[0].scrollHeight);
}

let counter = 1;

jQuery(document).ready(function($){
    jQuery('#file-import-button').click(function(){
        let file = document.getElementById("path").files[0];
        // TODO scrub inputs
        // console.log(file);


        output('preparing to process file...');

        let reader = new FileReader();
        reader.onload = function(e) {
            var data = e.target.result;
            // var wb = XLSX.read(data, {type: 'binary'});
            var uintArray = new Uint8Array(data);
            var arr = '';
            uintArray.forEach(function(byte) {arr = arr + String.fromCharCode(byte)});
            // var arr = String.fromCharCode.apply(null, new Uint8Array(data));
            var wb = XLSX.read(btoa(arr), {type: 'base64'});

            var workbook = [];
            wb.SheetNames.forEach(function(sheetName) {
                var roa = XLSX.utils.sheet_to_row_object_array(wb.Sheets[sheetName]);
                if(roa.length > 0){
                    workbook['sheetName'] = roa;
                }
            });

            output('successfully processed file');

            let products = workbook['sheetName']; // Array of products (objects)


            output('it contains ' + products.length + ' products')


            output('sending products to importer...');

            let counter = 0;


            (function() {
                let index = 0;

                function loadProduct() {
                    if (index < products.length) {
                        let product = products[index];
                        if(product['Qty'] !== '0'){
                            setTimeout(function(){
                                jQuery.post(controller_url + '/import', product, function (response) {
                                    if(response !== null){
                                        output(response);
                                    }else{
                                        output("an error occurred!");
                                    }
                                });
                                index++;
                                loadProduct();
                            }, 2000);


                        }else{
                            index++;
                            output('Skipping product ' + product['Sku'] + ' with quantity of 0');
                            loadProduct();
                        }

                    }else{
                        setTimeout(function(){
                            output('Import finished successfully!');
                        }, 5000);
                    }
                }
                loadProduct();

            })();


/*

            products.forEach(function(product){
                if(product['Qty'] !== '0'){
                    jQuery.post(controller_url, product, function (response) {
                        if(response !== null){
                            output(response);
                            counter++;
                            if(counter == products.length){
                                output('Finished!');
                            }
                        }else{
                            counter++;
                            if(counter == products.length){
                                output('Finished!');
                            }
                        }
                    });
                }else{
                    console.log('skipping one');
                }

            });
*/







        };
        //reader.readAsBinaryString(f);
        reader.readAsArrayBuffer(file);




        /*
        let data =  {'product_action': importType, 'product_data': 'TODO'};

        jQuery.post(controller_url, data, function (response) {
            console.log('clicked');
            alert(response);
        });
        */
    });


});