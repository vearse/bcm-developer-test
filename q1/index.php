<?php

require __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/bcm-helpers.php';

$page   = bcm_page_param($_GET);
$search = bcm_search_param($_GET);

$q = new WP_Query([
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'posts_per_page'      => 10,
    'paged'               => $page,
    's'                   => $search,
    'ignore_sticky_posts' => true,
]);

$pages   = max(1, (int) $q->max_num_pages);
$ad_slot = 0;
$list_url = home_url('/bcm-test/q1/test.php');
?>
<h1>
    Page <?php echo (int) $page; ?> of <?php echo (int) $pages; ?>
    <?php if ($search !== '') : ?>: Search for <?php echo esc_html($search); ?><?php endif; ?>
</h1>

<?php if ($q->have_posts()) : ?>

    <?php while ($q->have_posts()) : $q->the_post(); ?>
        <?php
        $content = apply_filters('the_content', get_the_content());
        $content = str_replace(']]>', ']]&gt;', $content);
        $content = bcm_inject_ads($content, $ad_slot);
        ?>
        <article id="post-<?php the_ID(); ?>">
            <h2><a href="<?php the_permalink(); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
            <div class="entry-content"><?php echo $content; ?></div>
        </article>
    <?php endwhile; ?>
    <?php wp_reset_postdata(); ?>

    <?php if ($pages > 1) : ?>
        <nav class="pagination" aria-label="Pages">
            <?php
            echo paginate_links([
                'base'     => trailingslashit($list_url) . '%_%',
                'format'   => '?page=%#%',
                'current'  => $page,
                'total'    => $pages,
                'add_args' => $search !== '' ? ['s' => $search] : false,
            ]);
            ?>
        </nav>
    <?php endif; ?>

<?php else : ?>

    <p class="no-results">
        <?php if ($page > 1) : ?>
            There is no page <?php echo (int) $page; ?> for this search.
            <a href="<?php echo esc_url(add_query_arg(['s' => $search], $list_url)); ?>">Back to page 1</a>
        <?php elseif ($search !== '') : ?>
            No posts found for &ldquo;<?php echo esc_html($search); ?>&rdquo;.
        <?php else : ?>
            No posts found.
        <?php endif; ?>
    </p>

<?php endif; ?>
