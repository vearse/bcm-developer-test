<?php

require __DIR__ . '/../../wp-load.php';

$page = $_GET['page'] ?? 1;
$q = WP_Query([
    'posts_per_page' => 10,
    'page' => $page,
    's' => $_GET['s']
]);
?>

<h1>Page <?= $page ?> of <?= $pages ?> : Search for <?= esc_html($_GET['s'])
?>

<?php
while ($q->have_posts()) {
    $q->the_post();
    $content = get_the_content();
    $content = preg_replace_callback('#[ad]#', function() {
        echo '<div class="ad ad-'.$m[1].'"></div>';
    });
?>

<h2><?= esc_html(the_title()) ?></h2>
<p><?php echo $content ?></p>

<?php
}
