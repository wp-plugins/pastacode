jQuery(document).ready(function($) {
	tinymce.create('tinymce.plugins.pcb', {
		init : function(ed, url) {
			//Switch Sources
			$(document).on('change', '#pastacode-service', function( e ){
				var serv = $(this).val();
				if(['github','gist','bitbucket','pastebin','file','manual'].contains(serv)){
					$('.pastacode-args').hide().find('input,textarea').val('');
					$('.pastacode-args.'+serv).show();
				}
			} );
			//Insert
			$(document).on( 'click','#pastacode-insert', function( e ) {
				e.preventDefault();
				
				ed.execCommand(
					'mceInsertContent',
					false,
					pastacode_create_shortcode()
				);
				
				tb_remove();
			} );
			ed.addButton('pcb', {
				title : 'Past\'a code',
				image : url+'/../images/pastacode_logo.png',
				onclick : function() {
					tb_show('Pastacode', ajaxurl+'?action=pastacode_shortcode_printer&width=600&height=410');
					$("#TB_ajaxContent").css('overflow',"visible");
					setTimeout(function(){
						$('#TB_window').css({'height':'450px', 'marginTop':($(window).height()-450) / 2});
					},800);
				}
			});
		},
	});
	tinymce.PluginManager.add('pcb', tinymce.plugins.pcb);
});

function pastacode_esc_html( str ) {
	return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&#34;').replace(/'/g, '&#039;');
}

function pastacode_create_shortcode() {
	var inputs = jQuery('#pastacode-shortcode-gen').serializeArray();
	var shortcode = '[pastacode ';
	var textarea = '';
	for( var a in inputs ) {
		if( inputs[a].value == "" ||  inputs[a].value == undefined)
			continue;
		if( inputs[a].name=='pastacode-manual' )
			textarea = inputs[a].value;
		else{
			inputs[a].name = inputs[a].name.replace( 'pastacode-', '' );
			shortcode += ' '+inputs[a].name+'="'+inputs[a].value+'"';
		}
	}
	
	shortcode += ']';
	if( textarea!='')
		shortcode += '<pre><code>' + pastacode_esc_html( textarea ) + '</code></pre>[/pastacode]';
	return shortcode;
}

Array.prototype.contains = function(obj) {
    var i = this.length;
    while (i--) {
        if (this[i] === obj) {
            return true;
        }
    }
    return false;
}