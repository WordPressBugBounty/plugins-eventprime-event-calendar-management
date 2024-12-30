<?php defined( 'ABSPATH' ) || exit;
$ep_functions = new Eventprime_Basic_Functions;
?>
<div class="wrap">
    <div class="ep-box-row ep-justify-content-between ep-px-2">
        <div class="ep-box-col-6">
            <h2>
                <?php
                esc_html_e( 'Attendee List:- ', 'eventprime-event-calendar-management' );?> 
                <?php esc_html_e(get_the_title( $event_id ));?>
            </h2>
        </div>
        <div class="ep-box-col-5 ep-text-end ep-pt-3 ep-d-flex ep-justify-content-end ep-align-items-center">
            
            <span class="spinner" id="ep_print_attendee_list_loader"></span>
            <button type="button" id="ep_print_event_attendees_list" name="ep_print_event_attendees_list" class="button-primary ep-save-button">
                <?php esc_html_e( 'Download', 'eventprime-event-calendar-management' );?> 
            </button>
            <?php wp_nonce_field( 'ep_print_event_attendees', 'ep_ep_print_event_attendees_nonce' );?>
        </div>
    </div>
    <div class="ep-justify-content-between">
        <?php do_action('ep_add_filter_in_attendee_list_page'); ?>
    </div>
    <input type="hidden" id="ep_event_id" value="<?php esc_attr_e( $event_id );?>">
    <input type="hidden" id="attendee_check_in_filter" value="<?php esc_attr_e( $filter_value );?>">
    <input type="hidden" id="ep_attendee_page_user_filter" value="<?php esc_attr_e( $user_filter_value );?>">
    <table class="form-table ep-attendee-check-in-list">
        <tr> 
            <td class="ep-px-0">
                <table class="ep-setting-table ep-setting-table-wide">
                    <thead>
                        <tr>
                            <?php do_action('ep_add_attendee_field_heading_front');?>
                            <th><?php esc_html_e('#','eventprime-event-calendar-management');?></th>
                            <?php if( empty( $attendee_fileds_data ) ) {?>
                                <th><?php esc_html_e( 'First Name', 'eventprime-event-calendar-management' );?></th>
                                <th><?php esc_html_e( 'Last Name', 'eventprime-event-calendar-management' );?></th><?php
                            } else{
                                if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name'] ) ) {
                                    if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_first_name'] ) ) {?>
                                        <th><?php esc_html_e( 'First Name', 'eventprime-event-calendar-management' );?></th><?php
                                    }
                                    if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_middle_name'] ) ) {?>
                                        <th><?php esc_html_e( 'Middle Name', 'eventprime-event-calendar-management' );?></th><?php
                                    }
                                    if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_last_name'] ) ) {?>
                                        <th><?php esc_html_e( 'Last Name', 'eventprime-event-calendar-management' );?></th><?php
                                    }
                                }
                                foreach( $attendee_fileds_data as $fields ) {
                                    $label = $ep_functions->get_checkout_field_label_by_id( $fields );?>
                                    <th><?php echo esc_html( $label->label );?></th><?php
                                }
                            }?>
                            <th><?php esc_html_e( 'User Email', 'eventprime-event-calendar-management' );?></th>
                            <th><?php esc_html_e( 'Ticket', 'eventprime-event-calendar-management' );?></th>
                            <th><?php esc_html_e( 'Booked On', 'eventprime-event-calendar-management' );?></th>
                            <?php do_action('ep_add_attendee_field_heading');?>
                        </tr>
                    </thead>
                    <tbody id="ep-feedback-list-body"><?php
                        $num = 1;
                        $booking_controller = new EventPrime_Bookings;
                        $event_bookings = $booking_controller->get_event_bookings_by_event_id( $event_id );
                        if( ! empty( $event_bookings ) ) {
                            foreach( $event_bookings as $booking ) {
                                $booking_id = $booking->ID;
                                $em_attendee_names = get_post_meta( $booking_id, 'em_attendee_names', true );
                                if( ! empty( $em_attendee_names ) ) {
                                    $ticket_name = '';
                                    foreach( $em_attendee_names as $ticket_id => $ticket_attendees ) {
                                        if( ! empty( $ticket_id ) ) {
                                            $ticket_name = $ep_functions->get_ticket_name_by_id( $ticket_id );
                                        }
                                        if( ! empty( $ticket_attendees ) && count( $ticket_attendees ) > 0 ) {
                                            $att_count = 1;
                                            foreach( $ticket_attendees as $attendee_data ) {
                                                $filter_attendee = apply_filters('attendee_check_in_filter',$attendee_data, $booking_id, $ticket_id, $att_count);
                                                if(!empty($filter_attendee))
                                                {
                                                ?>
                                                <tr>
                                                <?php do_action('ep_add_attendee_field_heading_data_front', $booking_id, $ticket_id, $att_count, $attendee_data); ?>
                                                    <td><?php echo esc_html( $num );?></td><?php
                                                    if( empty( $attendee_fileds_data ) ) {?>
                                                        <td><?php echo ( ! empty( $attendee_data['name']['first_name'] ) ? esc_html( $attendee_data['name']['first_name'] ) : '----' );?></td>
                                                        <td><?php echo ( ! empty( $attendee_data['name']['last_name'] ) ? esc_html( $attendee_data['name']['last_name'] ) : '----' );?></td><?php
                                                    } else{
                                                        if( isset( $attendee_data['name'] ) ) {
                                                            if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_first_name'] ) ) {?>
                                                                <td><?php echo ( ! empty( $attendee_data['name']['first_name'] ) ? esc_html( $attendee_data['name']['first_name'] ) : '----' );?></td><?php
                                                            }
                                                            if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_middle_name'] ) ) {?>
                                                                <td><?php echo ( ! empty( $attendee_data['name']['middle_name'] ) ? esc_html( $attendee_data['name']['middle_name'] ) : '----' );?></td><?php
                                                            } if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_last_name'] ) ) {?>
                                                                <td><?php echo ( ! empty( $attendee_data['name']['last_name'] ) ? esc_html( $attendee_data['name']['last_name'] ) : '----' );?></td><?php
                                                            }
                                                        } else { 
                                                            if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_first_name'] ) ) {?>
                                                                <td>----</td><?php
                                                            }
                                                            if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_middle_name'] ) ) {?>
                                                                <td>----</td><?php
                                                            } if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_last_name'] ) ) {?>
                                                                <td>----</td><?php
                                                            }
                                                        }
                                                        foreach( $attendee_fileds_data as $fields ) {?>
                                                            <td><?php
                                                                $field_value = '';
                                                                if( ! empty( $attendee_data[$fields] ) ) {
                                                                    $label_val = $attendee_data[$fields]['label'];
                                                                    $input_name = $ep_functions->ep_get_slug_from_string( $label_val );
                                                                    if( ! empty( $attendee_data[$fields][$input_name] ) ) {
                                                                        $input_val = $attendee_data[$fields][$input_name];
                                                                        if( is_array( $input_val ) ) {
                                                                            $field_value = implode( ', ', $input_val );
                                                                        } else{
                                                                            $field_value = $attendee_data[$fields][$input_name];
                                                                        }
                                                                    }
                                                                } 
                                                                if( ! empty( $field_value ) ) {
                                                                    echo esc_html( $field_value );
                                                                }else{
                                                                    echo '----';
                                                                }?>
                                                            </td><?php
                                                        }
                                                    }?>
                                                    <td>
                                                        <?php $user_id = get_post_meta( $booking_id, 'em_user', true );
                                                        if( ! empty( $user_id ) ) {
                                                            $user = get_user_by( 'id', $user_id );
                                                            if( ! empty( $user ) ) {
                                                                echo esc_html( $user->user_email );
                                                            } else{
                                                                echo '----';
                                                            }
                                                        } else{
                                                            $is_guest_booking = get_post_meta( $booking_id, 'em_guest_booking', true );
                                                            if( ! empty( $is_guest_booking ) ) {
                                                                $em_order_info = get_post_meta( $booking_id, 'em_order_info', true );
                                                                if( ! empty( $em_order_info ) && ! empty( $em_order_info['user_email'] ) ) {
                                                                    echo esc_html( $em_order_info['user_email'] );
                                                                }
                                                            }
                                                        }?>
                                                    </td>
                                                    <td>
                                                        <?php if( ! empty( $ticket_id ) ) {
                                                            echo esc_html( $ticket_name );
                                                        } else{
                                                            echo '----';
                                                        }?>
                                                    </td>
                                                    <td>
                                                        <?php $em_date = get_post_meta( $booking_id, 'em_date', true );
                                                        if( ! empty( $em_date ) ) {
                                                            echo esc_html( $ep_functions->ep_timestamp_to_date( $em_date, 'd M, Y' ) );
                                                        }?>
                                                    </td>

                                                    <?php do_action('ep_add_attendee_field_heading_data', $booking_id, $ticket_id, $att_count, $attendee_data); ?>
                                                </tr>
                                                <?php 
                                                // print_r($num);
                                                $num++;
                                            }
                                            // print_r($att_count);
                                            $att_count++;   
                                            }
                                        }
                                    }
                                } else{
                                    $tickets_info = ( ! empty( $booking->em_order_info['tickets'] ) ? $booking->em_order_info['tickets'] : array() );
                                    if( ! empty( $tickets_info ) && count( $tickets_info ) > 0 ) {
                                        for( $con = 0; $con < count( $tickets_info ); $con++ ) {
                                            $ticket_id = ( ! empty( $tickets_info[$con] ) && ! empty( $tickets_info[$con]->id ) ) ? $tickets_info[$con]->id : '';
                                            $ticket_name = ( ! empty( $ticket_id ) ? $ep_functions->get_ticket_name_by_id( $ticket_id ) : '----' ); ?>
                                            <tr>
                                                <td><?php echo esc_html( $con + 1 );?></td><?php
                                                if( empty( $attendee_fileds_data ) ) {?>
                                                    <td><?php echo '----';?></td>
                                                    <td><?php echo '----';?></td><?php
                                                } else{
                                                    if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name'] ) ) {
                                                        if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_first_name'] ) ) {?>
                                                            <td><?php echo '----';?></td><?php
                                                        }
                                                        if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_middle_name'] ) ) {?>
                                                            <td><?php echo '----';?></td><?php
                                                        }
                                                        if( ! empty( $em_event_checkout_attendee_fields['em_event_checkout_name_last_name'] ) ) {?>
                                                            <td><?php echo '----';?></td><?php
                                                        }
                                                    }
                                                    for( $att_con = 0; $att_con < count( $attendee_fileds_data ); $att_con++ ) {?>
                                                        <td><?php echo '----';?></td><?php
                                                    }
                                                }?>
                                                <td>
                                                    <?php $user_id = get_post_meta( $booking_id, 'em_user', true );
                                                    if( ! empty( $user_id ) ) {
                                                        $user = get_user_by( 'id', $user_id );
                                                        if( ! empty( $user ) ) {
                                                            echo esc_html( $user->user_email );
                                                        } else{
                                                            echo '----';
                                                        }
                                                    } else{
                                                        $is_guest_booking = get_post_meta( $booking_id, 'em_guest_booking', true );
                                                        if( ! empty( $is_guest_booking ) ) {
                                                            $em_order_info = get_post_meta( $booking_id, 'em_order_info', true );
                                                            if( ! empty( $em_order_info ) && ! empty( $em_order_info['user_email'] ) ) {
                                                                echo esc_html( $em_order_info['user_email'] );
                                                            }
                                                        }
                                                    }?>
                                                </td>
                                                <td>
                                                    <?php echo esc_html( $ticket_name );?>
                                                </td>
                                                <td>
                                                    <?php $em_date = get_post_meta( $booking_id, 'em_date', true );
                                                    if( ! empty( $em_date ) ) {
                                                        echo esc_html( $ep_functions->ep_timestamp_to_date( $em_date, 'd M, Y' ) );
                                                    }?>
                                                </td>
                                            </tr><?php
                                        }
                                    }
                                }
                            }
                        }?>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</div>