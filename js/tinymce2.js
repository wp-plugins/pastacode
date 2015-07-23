(function( window, views, $ ) {

	function fields( provider, pfields, editor, values ) {
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

	function theFunction( key, editor, pvars ) {
		var fn2 = function() {
			editor.windowManager.open( {
				title: pastacodeText['window-title'] + ' - ' + pvars[key],
				body: fields( key, pastacodeVars['fields'], editor ),
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
					editor.nodeChanged();
				}
			} );
		};
		return fn2;
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

	tinymce.PluginManager.add('pcb', function( editor, url ) {

		editor.addButton('pcb', {
			icon: 'pcb-icon',
			type: 'menubutton',
			menu : providers(editor,pastacodeVars['providers'])
		});

		views.register( 'pastacode', {
		    initialize: function() {
			    var self = this;
			    var titre = '<div class="pastacode-view"><p><strong>Pastacode</strong></p>';
			    var provider = getAttr( this.text, 'provider' );
			    titre += '<p>' + provider;
			    switch ( provider ) {
					case 'manual' :
						if ( getAttr( this.text, 'message' ) ) {
							titre += ' : ' + getAttr( this.text, 'message' );
						}
						break;
					default : 
						titre += ' : ' + getAttr( this.text, 'path_id' );
				}
				var l = getAttr( this.text, 'lines' );
				if ( l ) {
					titre += ' (' + l + ')';
				}
				titre += '</p></div>';
			    self.render( titre );
			},
			edit: function( text, update ) {
				var provider = getAttr(text, 'provider' );
				var values = [];
				for ( var field in pastacodeVars['fields'] ) {
					if ( pastacodeVars['fields'][field].name == 'manual' ) {
						console.log(text);
						values[field] = getShortcodeContent( text );
					} else {
						values[field] = getAttr(text, pastacodeVars['fields'][field].name );
					}
				}

				var fn = theFunction( provider, editor, pastacodeVars['providers']);

				editor.windowManager.open( {
					title: pastacodeText['window-title'] + ' - ' + pastacodeVars['providers'][provider],
					body: fields( provider, pastacodeVars['fields'], editor, values),
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
						update( out );
					}
				});
			},
		} );

	});

	function getAttr( str, name ) {
		name = new RegExp( name + '=\"([^\"]+)\"' ).exec( str );
		return name ? window.decodeURIComponent( name[1] ) : '';
	}

	function getShortcodeContent( str ) {
		var content = new RegExp( /<pre><code>([\s\S]*)<\/code><\/pre>/ ).exec(str);
		return content ? content[1].replace( /&amp;/g, '&').replace(/&lt;/g, '<' ).replace(/&gt;/g, '>').replace(/&#34;/g, '"').replace(/&#039;/g, "'") : '';
	}

	function pastacode_esc_html( str ) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&#34;').replace(/'/g, '&#039;');
	}
})( window, window.wp.mce.views, window.jQuery );