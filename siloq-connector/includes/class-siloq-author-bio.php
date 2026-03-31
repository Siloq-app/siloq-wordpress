<?php
/**
 * Siloq Author Bio Block
 *
 * Renders a professional author bio block at the bottom of single post content
 * using Siloq custom fields for E-E-A-T author attribution.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Siloq_Author_Bio {

    public function __construct() {
        add_filter( 'the_content', array( $this, 'append_author_bio' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    /**
     * Append author bio block to single post content.
     */
    public function append_author_bio( $content ) {
        if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }

        $post_id = get_the_ID();

        $author_name     = get_post_meta( $post_id, '_siloq_author_name', true );
        $author_title    = get_post_meta( $post_id, '_siloq_author_title', true );
        $author_bio      = get_post_meta( $post_id, '_siloq_author_short_bio', true );
        $linkedin_url    = get_post_meta( $post_id, '_siloq_author_linkedin', true );
        $photo_url       = get_post_meta( $post_id, '_siloq_author_photo_url', true );
        $author_page_url = get_post_meta( $post_id, '_siloq_author_page_url', true );
        $credentials_raw = get_post_meta( $post_id, '_siloq_author_credentials', true );

        if ( empty( $author_name ) ) {
            return $content;
        }

        $credentials = array();
        if ( ! empty( $credentials_raw ) ) {
            $decoded = json_decode( $credentials_raw, true );
            if ( is_array( $decoded ) ) {
                $credentials = $decoded;
            }
        }

        $bio_html = $this->render_bio_block(
            $author_name,
            $author_title,
            $author_bio,
            $linkedin_url,
            $photo_url,
            $author_page_url,
            $credentials
        );

        return $content . $bio_html;
    }

    /**
     * Render the author bio HTML block.
     */
    private function render_bio_block( $name, $title, $bio, $linkedin_url, $photo_url, $page_url, $credentials ) {
        ob_start();
        ?>
        <div class="siloq-author-bio" itemscope itemtype="https://schema.org/Person">
            <div class="siloq-author-bio-inner">
                <div class="siloq-author-avatar">
                    <?php if ( ! empty( $photo_url ) ) : ?>
                        <img class="siloq-author-photo"
                             src="<?php echo esc_url( $photo_url ); ?>"
                             alt="<?php echo esc_attr( $name ); ?>"
                             itemprop="image"
                             width="80" height="80" />
                    <?php else :
                        $parts    = explode( ' ', trim( $name ) );
                        $initials = strtoupper( substr( $parts[0], 0, 1 ) );
                        if ( count( $parts ) > 1 ) {
                            $initials .= strtoupper( substr( end( $parts ), 0, 1 ) );
                        }
                    ?>
                        <div class="siloq-author-initials"><?php echo esc_html( $initials ); ?></div>
                    <?php endif; ?>
                </div>

                <div class="siloq-author-info">
                    <div class="siloq-author-header">
                        <div class="siloq-author-identity">
                            <span class="siloq-author-label">Written by</span>
                            <?php if ( ! empty( $page_url ) ) : ?>
                                <a class="siloq-author-name-link" href="<?php echo esc_url( $page_url ); ?>" itemprop="name"><?php echo esc_html( $name ); ?></a>
                            <?php else : ?>
                                <span class="siloq-author-name" itemprop="name"><?php echo esc_html( $name ); ?></span>
                            <?php endif; ?>
                            <?php if ( ! empty( $title ) ) : ?>
                                <span class="siloq-author-title" itemprop="jobTitle"><?php echo esc_html( $title ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( ! empty( $linkedin_url ) ) : ?>
                            <a class="siloq-author-linkedin" href="<?php echo esc_url( $linkedin_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn profile" itemprop="sameAs">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ( ! empty( $credentials ) ) : ?>
                        <div class="siloq-author-credentials">
                            <?php foreach ( array_slice( $credentials, 0, 4 ) as $cred ) :
                                if ( ! empty( $cred['name'] ) ) : ?>
                                    <span class="siloq-author-credential"><?php echo esc_html( $cred['name'] ); ?></span>
                                <?php endif;
                            endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $bio ) ) : ?>
                        <p class="siloq-author-bio-text" itemprop="description"><?php echo esc_html( $bio ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue author bio stylesheet on single posts.
     */
    public function enqueue_styles() {
        if ( ! is_single() ) {
            return;
        }
        wp_enqueue_style(
            'siloq-author-bio',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/author-bio.css',
            array(),
            '1.5.271'
        );
    }
}
