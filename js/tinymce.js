(function() {

	function fields( provider, pfields, values, editor ) {
		var fields = [];
		for ( var k in pfields ) {

			if ( typeof values != 'undefined' && typeof values[k] != 'undefined' ) {
				pfields[k].value = values[k];
			} else {
				pfields[k].value = '';
			}

			if ( typeof pfields[k]['classes'] != 'undefined' ) {
				if ( pfields[k]['classes'].indexOf( provider ) != -1 ) {
					fields.push( pfields[k] );
				}
			}else {
				if ( pfields[k]['name'] == 'lang' ) {
					fields.push( pfields[k] );
				}
			}
			
			if ( typeof pfields[k]['multiline'] != 'undefined' && pfields[k]['multiline'] == true ) {
				pfields[k]['onpostRender'] = function() {
					var thing = this;
					this.before( {
						type:'button',
						text:' ',
						tooltip:pastacodeVars['extendText'],
						style:'background:none;-moz-box-shadow:none;-webkit-box-shadow:none;box-shadow:none;margin-left:-10px;background:url(' + pastacodeVars['extendIcon'] + ');width:32px;height:32px;',
						border:'0',
						onclick: function() {
							editor.windowManager.open( {
								title: pastacodeText['window-title'] + ' - ' + pastacodeText['window-manuel-full'],
								minHeight:window.innerHeight - 100,
								minWidth:window.innerWidth - 50,
								body:[{
									type:'textbox', 
									multiline:true, 
									minHeight:window.innerHeight - 160,
									name:'newCode',
									value : thing.value(),
									onPostRender: function() {
										var textarea = this.getEl().getAttribute( 'id' );
										setTimeout( function () {
											jQuery( document ).ready( function($) {
												$( '#' + textarea ).css({marginLeft:'30px'}).linenumbers({col_width:'20px'});
											} );
										}, 200 );
									}
								}],
								onsubmit:function( e ){
									thing.value( e.data.newCode );
								},
							} );
						},
					});
				};
			}
		}

		fields.push( {
			type: 'textbox',
			visible: false,
			value: provider,
			name:'provider'
		} );
		return fields;
	}

	function mycustomfonct(editor){
		console.log(editor);
	}

	function theFunction( key, editor, pvars ) {
		fn = function() {
			editor.windowManager.open( {
				title: pastacodeText['window-title'] + ' - ' + pvars[key],
				body: fields( key, pastacodeVars['fields'], '', editor ),
				onsubmit: function( e ) {
					var out = '';
					if( e.data['provider'] == 'manual' ) {
						var manual = e.data.manual;
						delete e.data.manual
						out += '[pastacode';
						for ( var attr in e.data ) {
							out += ' ' + attr + '="' + e.data[ attr ] + '"';
						}
						out += ']<pre><code>' + pastacode_esc_html( manual ) + '</code></pre>[/pastacode]';
					} else {
						out += '[pastacode';
						for ( var attr in e.data ) {
							out += ' ' + attr + '="' + e.data[ attr ] + '"';
						}
						out += '/]';
					}
					editor.insertContent( out );
				}
			} );
		};
		return fn;
	}

	function providers( editor, pvars ) {
		var providers = [];
		for (var key in pvars ) {
			var provider = new Object();
			provider.text = pvars[key];
			provider.onclick = theFunction( key, editor, pvars );
			providers.push( provider );
		};
		return providers;
	}

	function pastacode_esc_html( str ) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&#34;').replace(/'/g, '&#039;');
	}

	tinymce.PluginManager.add('pcb', function( editor, url ) {

		editor.addButton('pcb', {
			icon: 'pcb-icon',
			type: 'menubutton',
			menu : providers(editor,pastacodeVars['providers'])
		});

        // Replace shortcode
		editor.on( 'BeforeSetContent', function( event ) {
			event.content = replacePastacodeShortcodes( event.content );
		});

		// Restore shortcode
		editor.on( 'PostProcess', function( event ) {
			if ( event.get ) {
				event.content = restorePastacodeShortcodes( event.content );
			}
		});

		// Edit shortcode
		editor.on( 'mouseup', function( event ) {
			var dom = editor.dom,
				node = event.target;

			function unselect() {
				dom.removeClass( dom.select( 'div.wp-pastacode-selected' ), 'wp-pastacode-selected' );
			}

			if ( ( node.nodeName === 'DIV' && dom.getAttrib( node, 'data-wp-pastacode' ) ) || ( node.nodeName === 'SPAN' && dom.getAttrib( dom.getParents(node)[1], 'data-wp-pastacode' ) ) ) {
				// Don't trigger on right-click
				if ( event.button !== 2 ) {
					if ( dom.hasClass( node, 'wp-pastacode-selected' ) || dom.hasClass( dom.getParents(node)[1], 'wp-pastacode-selected' ) ) {
						if ( node.nodeName === 'DIV' )
							editPastacode( node, editor );
						if ( node.nodeName === 'SPAN' )
							editPastacode( dom.getParents(node)[1], editor );
					} else {
						unselect();
						if ( node.nodeName === 'DIV' )
							dom.addClass( node, 'wp-pastacode-selected' );
						if ( node.nodeName === 'SPAN' )
							dom.addClass( dom.getParents(node)[1], 'wp-pastacode-selected' );
					}
				}
			} else {
				if ( node.nodeName === 'BUTTON' && dom.hasClass( dom.getParents(node)[1], 'wp-pastacode-selected' ) && event.button !== 2 ) { //
					if ( dom.hasClass( node, 'remove' ) ) {
						dom.remove(dom.getParents(node)[1]);
					} else {
						editPastacode( dom.getParents( node )[1], editor );
					}
				} else {
					unselect();
				}
			}
		});
	});

	var styleDiv = ' contenteditable="false"';

	// Replace shortcode
	function replacePastacodeShortcodes( content ) {
		var pastacodeShortcodeRegex = new RegExp( '\\[(\\[?)(pastacode)(?![\\w-])([^\\]\\/]*(?:\\/(?!\\])[^\\]\\/]*)*?)(?:(\\/)\\]|\\](?:([^\\[]*(?:\\[(?!\\/\\2\\])[^\\[]*)*)(\\[\\/\\2\\]))?)(\\]?)', 'g' );
		return content.replace( pastacodeShortcodeRegex, function( match ) {
			return htmlPastacode( 'wp-pastacode', match );
		});
	}

	function htmlPastacode( cls, data ) {
		switch ( getAttr( data, 'provider' ) ) {
			case 'manual' :
				var titre = getAttr( data, 'message' );
				break;
			default : var titre = getAttr( data, 'path_id' );
		}
		var l = getAttr( data, 'lines' )
		if ( l )
			titre += ' (' + l + ')';
		data = window.encodeURIComponent( data );
		return '<div style="background-image:url(' + pastacodeText['image-placeholder'] + ');" ' + styleDiv + ' class="pasta-item wp-media mceItem ' + cls + '" ' +
			'data-wp-pastacode="' + data + '" data-mce-resize="false" data-mce-placeholder="1" ><button class="dashicons dashicons-edit edit">x</button><button class="dashicons dashicons-no-alt remove">x</button><span class="pastacode-shortcode-title">' + titre + '</span></div><p></p>';
	}

	// Restore shortcode
	function restorePastacodeShortcodes( content ) {

		return content.replace( /(?:<p(?: [^>]+)?>)*(<div [^>]+>)(.*?)<\/div>(?:<\/p>)*/g, function( match, image ) {
			var data = getAttr( image, 'data-wp-pastacode' );

			if ( data ) {
				return '<p>' + data + '</p>';
			}

			return match;
		});
	}

	function getAttr( str, name ) {
		name = new RegExp( name + '=\"([^\"]+)\"' ).exec( str );
		return name ? window.decodeURIComponent( name[1] ) : '';
	}

	function getShortcodeContent( str ) {
		var content = new RegExp( "<pre><code>([^<]+)<\/code><\/pre>" ).exec(str);
		return content ? content[1].replace( /&amp;/g, '&').replace(/&lt;/g, '<' ).replace(/&gt;/g, '>').replace(/&#34;/g, '"').replace(/&#039;/g, "'") : '';
	}

	// Edit shortcode
	function editPastacode( node, editor ) {
		var gallery, frame, data;

		if ( node.nodeName !== 'DIV' ) {
			return;
		}

		data = window.decodeURIComponent( editor.dom.getAttrib( node, 'data-wp-pastacode' ) );

		// Make sure we've selected a Pastacode node.
		if ( editor.dom.hasClass( node, 'wp-pastacode' ) ) {
			var provider = getAttr(data, 'provider' );
			var values = [];
			for ( var field in pastacodeVars['fields'] ) {
				if ( pastacodeVars['fields'][field].name == 'manual' ) {
					values[field] = getShortcodeContent( data );
				} else {
					values[field] = getAttr(data, pastacodeVars['fields'][field].name );
				}
			}

			var fn = theFunction( provider, editor, pastacodeVars['providers'], values);

			editor.windowManager.open( {
				title: pastacodeText['window-title'] + ' - ' + pastacodeVars['providers'][provider],
				body: fields( provider, pastacodeVars['fields'], values, editor),
				onsubmit: function( e ) {
					var out = '';
					if( e.data['provider'] == 'manual' ) {
						var manual = pastacode_esc_html( e.data.manual );
						delete e.data.manual
						out += '[pastacode';
						for ( var attr in e.data ) {
							out += ' ' + attr + '="' + e.data[ attr ] + '"';
						}
						out += ']<pre><code>' + manual + '</code></pre>[/pastacode]';
					} else {
						out += '[pastacode';
						for ( var attr in e.data ) {
							out += ' ' + attr + '="' + e.data[ attr ] + '"';
						}
						out += '/]';
					}
					var newNode = editor.dom.createFragment( replacePastacodeShortcodes( out ) );
					editor.dom.replace( newNode, node );
				}
			});

		}
	}

})();