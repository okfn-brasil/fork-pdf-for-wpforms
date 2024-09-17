(function($) {
    "use strict";
    $( document ).ready( function () {
        $("body").on("change","#wpforms-panel-field-settings-pdf_creator_enable",function(e){
            if($(this).is(":checked")){
                $(".wpforms-pdf_creator").show().removeClass("hidden");
            }else{
                $(".wpforms-pdf_creator").hide().addClass("hidden");
            }
        })
        $("body").on("click",".wpforms-pdf_creator-add",function(e){
            if(yeepdf_wpforms.pro == "pro"){
                return;
            }
            var $el = $(this);
            var $builder = $("#wpforms-builder");
            var nextID = Number( $el.attr( 'data-next-id' ) ),
				panelID = $el.closest( '.wpforms-panel-content-section' ).data( 'panel' ),
				blockType = $el.data( 'block-type' ),
				namePrompt = wpforms_builder[ blockType + '_prompt' ],
				nameField = '<input autofocus="" type="text" id="settings-block-name" placeholder="' + wpforms_builder[ blockType + '_ph' ] + '">',
				nameError = '<p class="error">' + wpforms_builder[ blockType + '_error' ] + '</p>',
				modalContent = namePrompt + nameField + nameError;
                var modal = $.confirm( {
                    container: $builder,
                    title: false,
                    content: modalContent,
                    icon: 'fa fa-info-circle',
                    type: 'blue',
                    buttons: {
                        confirm: {
                            text: wpforms_builder.ok,
                            btnClass: 'btn-confirm',
                            keys: [ 'enter' ],
                            action: function() {
                                var settingsBlockName = $.trim( this.$content.find( 'input#settings-block-name' ).val() ),
                                    error = this.$content.find( '.error' );
                                if ( settingsBlockName === '' ) {
                                    error.show();
                                    return false;
                                } else {
                                    var $firstSettingsBlock = $el.closest( '.wpforms-panel-content-section' ).find( '.wpforms-builder-settings-block' ).first();
                                    // Restore tooltips before cloning.
                                    wpf.restoreTooltips( $firstSettingsBlock );
                                    var $newSettingsBlock = $firstSettingsBlock.clone(),
                                        blockID = $firstSettingsBlock.data( 'block-id' ),
                                        newSettingsBlock;
                                    $newSettingsBlock.attr( 'data-block-id', nextID );
                                    $newSettingsBlock.find( '.wpforms-builder-settings-block-header span' ).text( settingsBlockName );
                                    $newSettingsBlock.find( 'input, textarea, select' ).not( '.from-name input' ).not( '.from-email input' ).each( function( index, el ) {
                                        var $this = $( this );
                                        if ( $this.attr( 'name' ) ) {
                                            $this.val( '' ).attr( 'name', $this.attr( 'name' ).replace( /\[(\d+)\]/, '[' + nextID + ']' ) );
                                            if ( $this.is( 'select' ) ) {
                                                $this.find( 'option' ).prop( 'selected', false ).attr( 'selected', false );
                                                $this.find( 'option:first' ).prop( 'selected', true ).attr( 'selected', 'selected' );
                                            } else if ( $this.attr( 'type' ) === 'checkbox' ) {
                                                $this.prop( 'checked', false ).attr( 'checked', false ).val( '1' );
                                            } else {
                                                $this.val( '' ).attr( 'value', '' );
                                            }
                                        }
                                    } );
                                    // Update elements IDs.
                                    var idPrefixPanel = 'wpforms-panel-field-' + panelID + '-',
                                        idPrefixBlock = idPrefixPanel + blockID;
                                    $newSettingsBlock.find( '[id^="' + idPrefixBlock + '"], [for^="' + idPrefixBlock + '"]' ).each( function( index, el ) {
                                        var $el = $( this ),
                                            attr = $el.prop( 'tagName' ) === 'LABEL' ? 'for' : 'id',
                                            elID  = $el.attr( attr ).replace( new RegExp( idPrefixBlock, 'g' ), idPrefixPanel + nextID );
                                        $el.attr( attr, elID );
                                    } );
                                    // Update `notification by status` checkboxes.
                                    var radioGroup = blockID + '-notification-by-status';
                                    $newSettingsBlock.find( '[data-radio-group="' + radioGroup + '"]' ).each( function( index, el ) {
                                        $( this )
                                            .removeClass( 'wpforms-radio-group-' + radioGroup )
                                            .addClass( 'wpforms-radio-group-' + nextID + '-notification-by-status' )
                                            .attr( 'data-radio-group', nextID + '-notification-by-status' );
                                    } );
                                    $newSettingsBlock.find( '.wpforms-builder-settings-block-header input' ).val( settingsBlockName ).attr( 'value', settingsBlockName );
                                    if ( blockType === 'notification' ) {
                                        $newSettingsBlock.find( '.email-msg textarea' ).val( '{all_fields}' ).attr( 'value', '{all_fields}' );
                                        $newSettingsBlock.find( '.email-recipient input' ).val( '{admin_email}' ).attr( 'value', '{admin_email}' );
                                    }
                                    $newSettingsBlock.removeClass( 'wpforms-builder-settings-block-default' );
                                    if ( blockType === 'confirmation' ) {
                                        $newSettingsBlock.find( '.wpforms-panel-field-tinymce' ).remove();
                                        if ( typeof WPForms !== 'undefined' ) {
                                            $newSettingsBlock.find( '.wpforms-panel-field-confirmations-type-wrap' )
                                                .after( WPForms.Admin.Builder.Templates
                                                    .get( 'wpforms-builder-confirmations-message-field' )( {
                                                        id: nextID,
                                                    } )
                                                );
                                        }
                                    }
                                    // Conditional logic, if present
                                    var $conditionalLogic = $newSettingsBlock.find( '.wpforms-conditional-block' );
                                    if ( $conditionalLogic.length && typeof WPForms !== 'undefined' ) {
                                        $conditionalLogic
                                            .html( WPForms.Admin.Builder.Templates
                                                .get( 'wpforms-builder-conditional-logic-toggle-field' )( {
                                                    id: nextID,
                                                    type: blockType,
                                                    actions: JSON.stringify( $newSettingsBlock.find( '.wpforms-panel-field-conditional_logic-checkbox' ).data( 'actions' ) ),
                                                    actionDesc: $newSettingsBlock.find( '.wpforms-panel-field-conditional_logic-checkbox' ).data( 'action-desc' ),
                                                } )
                                            );
                                    }
                                    // Fields Map Table, if present.
                                    var $fieldsMapTable = $newSettingsBlock.find( '.wpforms-field-map-table' );
                                    if ( $fieldsMapTable.length ) {
                                        $fieldsMapTable.each( function( index, el ) {
                                            var $table = $( el );
                                            // Clean table fields.
                                            $table.find( 'tr:not(:first-child)' ).remove();
                                            var $input  = $table.find( '.key input' ),
                                                $select = $table.find( '.field select' ),
                                                name    = $select.data( 'name' );
                                            $input.attr( 'value', '' );
                                            $select
                                                .attr( 'name', '' )
                                                .attr( 'data-name', name.replace( /\[(\d+)\]/, '[' + nextID + ']' ) );
                                        } );
                                    }
                                    newSettingsBlock = $newSettingsBlock.wrap( '<div>' ).parent().html();
                                    newSettingsBlock = newSettingsBlock.replace( /\[conditionals\]\[(\d+)\]\[(\d+)\]/g, '[conditionals][0][0]' );
                                    $firstSettingsBlock.before( newSettingsBlock );
                                    var $addedSettingBlock = $firstSettingsBlock.prev();
                                    // Reset the confirmation type to the 1st one.
                                    if ( blockType === 'confirmation' ) {
                                        app.confirmationFieldsToggle( $( '.wpforms-panel-field-confirmations-type' ).first() );
                                    }
                                    // Init the WP Editor.
                                    if ( typeof tinymce !== 'undefined' && typeof wp.editor !== 'undefined' && blockType === 'confirmation' ) {
                                        wp.editor.initialize( 'wpforms-panel-field-confirmations-message-' + nextID, s.tinymceDefaults );
                                    }
                                    // Init tooltips for new section.
                                    wpf.initTooltips();
                                    $builder.trigger( 'wpformsSettingsBlockAdded', [ $addedSettingBlock ] );
                                    $el.attr( 'data-next-id', nextID + 1 );
                                }
                            },
                        },
                        cancel: {
                            text: wpforms_builder.cancel,
                        },
                    },
                } );
        })
    })
})(jQuery);