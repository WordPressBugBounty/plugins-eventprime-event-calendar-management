<?php
/**
 * View: Organizer List - List View
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/eventprime/organizer/list/views/list.php
 *
 */
?>
<?php foreach ( $args->organizers->terms as $organizer ) {?>
    <div class="ep-box-col-12 ep-list-view-main ep-mb-4">
        <div class="ep-box-row ep-bg-white ep-border ep-rounded ep-text-small ep-overflow-hidden">
            <div class="ep-box-col-3 ep-p-0 ep-bg-light ep-border-right ep-position-relative">
                <a href="<?php echo esc_url( $organizer->organizer_url ); ?>" class="ep-img-link">
                    <img src="<?php echo esc_url( $organizer->image_url ); ?>" alt="<?php echo esc_html( $organizer->name ); ?>">
                </a>
            </div>
            <div class="ep-box-col-6 ep-p-4 ep-text-small">
                <div class="ep-box-list-items">
                    <div class="ep-box-title ep-box-list-title">
                        <a class="ep-color-hover" data-organizer-id="<?php echo esc_attr( $organizer->id ); ?>" href="<?php echo esc_url( $organizer->organizer_url ); ?>" target="_self" rel="noopener">
                            <?php echo esc_html( $organizer->name ); ?>
                        </a>
                    </div>
                    <?php if ( ! empty( $organizer->description ) ) { ?>
                        <div class="ep-organizer-description ep-content-truncate ep-content-truncate-line-3">
                            <?php echo wpautop( wp_kses_post( $organizer->description ) ); ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="ep-box-col-3 ep-px-0 ep-pt-4 ep-border-left ep-position-relative">
                <ul class="ep-box-social-links ep-px-2 ep-text-end ep-d-inline-flex ep-justify-content-end ep-box-w-100 ep-m-0">
                    <?php
                    if ( ! empty( $organizer->em_social_links ) ) {
                        if ( ! empty( $organizer->em_social_links['facebook'] ) ) {?>
                            <li class="ep-event-social-icon ep-mr-2">
                                <a class="facebook" href="<?php echo esc_url( $organizer->em_social_links['facebook'] );?>" target="_blank" title="Facebook">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18px" viewBox="0 0 512 512">
                                        <path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z"/>
                                    </svg>
                                </a>
                            </li><?php
                        }
                        if ( ! empty( $organizer->em_social_links['instagram'] ) ) {?>
                            <li class="ep-event-social-icon ep-mr-2">
                                <a class="instagram" href="<?php echo esc_url( $organizer->em_social_links['instagram']);?>" target="_blank" title="Instagram">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18px" viewBox="0 0 448 512">
                                        <path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"/>
                                    </svg>
                                </a>
                            </li><?php
                        }
                        if ( ! empty( $organizer->em_social_links['linkedin'] ) ) {?>
                            <li class="ep-event-social-icon ep-mr-2">
                                <a class="linkedin" href="<?php echo esc_url( $organizer->em_social_links['linkedin']);?>" target="_blank" title="Linkedin">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18px" viewBox="0 0 448 512">
                                        <path d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79 53.79 0 0 1 107.58 0c0 29.7-24.1 54.3-53.79 54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94 0 111.28 61.9 111.28 142.3V448z"/>
                                    </svg>
                                </a>
                            </li><?php
                        }
                        if ( ! empty( $organizer->em_social_links['twitter'] ) ) {?>
                            <li class="ep-event-social-icon ep-mr-2">
                                <a class="twitter" href="<?php echo esc_url( $organizer->em_social_links['twitter']);?>" target="_blank" title="Twitter">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18px" viewBox="0 0 512 512">
                                        <path d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"/>
                                    </svg>
                                </a>
                            </li><?php
                        }
                        if ( ! empty( $organizer->em_social_links['youtube'] ) ) {?>
                            <li class="ep-event-social-icon ep-mr-2">
                                <a class="youtube" href="<?php echo esc_url( $organizer->em_social_links['youtube']);?>" target="_blank" title="YouTube">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18px" viewBox="0 0 576 512">
                                        <path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"/>
                                    </svg>
                                </a>
                            </li><?php
                        }
                    }?>                                
                </ul>
                <div class="ep-align-self-end ep-position-absolute ep-p-2 ep-bg-white ep-box-w-100"  style="bottom:0">
                    <a class="ep-view-details-button" data-event-id="<?php echo esc_attr( $organizer->id ); ?>" href="<?php echo esc_url( $organizer->organizer_url ); ?>">
                        <div class="ep-btn ep-btn-dark ep-box-w-100 ep-mb-2 ep-py-2">
                            <span class="ep-fw-bold ep-text-small">
                                <?php echo esc_html_e('View Detail', 'eventprime-event-calendar-management'); ?>								
                            </span>
                        </div>
                    </a>              
                </div>
            </div>
        </div>
    </div><?php 
} ?>