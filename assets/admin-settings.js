jQuery(document).ready(function($) {
    var mediaUploader;
    
    // Upload signature button
    $('#lrp_upload_signature_button').on('click', function(e) {
        e.preventDefault();
        
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Extend the wp.media object
        mediaUploader = wp.media({
            title: 'Seleccionar Firma del Proveedor',
            button: {
                text: 'Usar esta imagen'
            },
            library: {
                type: 'image/png'
            },
            multiple: false
        });
        
        // When a file is selected, run a callback
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Validate PNG format
            if (attachment.mime !== 'image/png') {
                alert('Error: Solo se permiten archivos PNG con fondo transparente.');
                return;
            }
            
            // Validate file size (2MB = 2097152 bytes)
            if (attachment.filesizeInBytes && attachment.filesizeInBytes > 2097152) {
                alert('Error: El archivo excede el tamaño máximo de 2MB.');
                return;
            }
            
            // Set the URL in the hidden field
            $('#lrp_provider_signature_url').val(attachment.url);
            
            // Show preview
            $('#lrp_signature_preview img').attr('src', attachment.url);
            $('#lrp_signature_preview').show();
            
            // Update button text
            $('#lrp_upload_signature_button').text('Cambiar Firma');
            
            // Show remove button if not already visible
            if ($('#lrp_remove_signature_button').length === 0) {
                $('#lrp_upload_signature_button').after(
                    '<button type="button" class="button" id="lrp_remove_signature_button" style="margin-left: 5px;">Eliminar Firma</button>'
                );
                // Re-bind the remove event
                bindRemoveButton();
            }
        });
        
        // Open the uploader dialog
        mediaUploader.open();
    });
    
    // Remove signature button
    function bindRemoveButton() {
        $('#lrp_remove_signature_button').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('¿Está seguro de que desea eliminar la firma del proveedor?')) {
                // Clear the URL field
                $('#lrp_provider_signature_url').val('');
                
                // Set delete flag
                $('#lrp_delete_signature').val('1');
                
                // Hide preview
                $('#lrp_signature_preview').hide();
                $('#lrp_signature_preview img').attr('src', '');
                
                // Update button text
                $('#lrp_upload_signature_button').text('Subir Firma');
                
                // Remove the delete button
                $(this).remove();
            }
        });
    }
    
    // Initial bind if button exists
    bindRemoveButton();
});
