<?php

add_action('rest_api_init', function () {
    register_rest_route('bcm/v1', '/articles/(?P<id>\d+)/status', [
        'methods'             => 'POST',
        'callback'            => 'bcm_set_article_status',
        'permission_callback' => 'bcm_can_set_article_status',
        'args'                => [
            'id'     => ['type' => 'integer', 'required' => true, 'minimum' => 1],
            'status' => ['type' => 'string',  'required' => true, 'enum' => ['draft', 'pending', 'publish']],
        ],
    ]);
});

function bcm_can_set_article_status(WP_REST_Request $request)
{
    if (!is_user_logged_in()) {
        return new WP_Error('bcm_unauthenticated', 'Log in first.', ['status' => 401]);
    }
    $post = get_post((int) $request['id']);
    if (!$post || $post->post_type !== 'post') {
        return new WP_Error('bcm_forbidden', 'Not allowed.', ['status' => 403]);
    }
    if (!current_user_can('edit_post', $post->ID)) {
        return new WP_Error('bcm_forbidden', 'Not allowed.', ['status' => 403]);
    }
    if ($request['status'] === 'publish' && !current_user_can('publish_post', $post->ID)) {
        return new WP_Error('bcm_forbidden', 'Not allowed.', ['status' => 403]);
    }
    return true;
}

function bcm_set_article_status(WP_REST_Request $request)
{
    $post = get_post((int) $request['id']);
    $old  = $post->post_status;
    $new  = $request['status'];

    $allowed = ['draft' => ['pending', 'publish'], 'pending' => ['draft', 'publish'], 'publish' => ['draft']];
    if (!in_array($new, $allowed[$old] ?? [], true)) {
        return new WP_Error('bcm_bad_transition', "Cannot change from $old to $new.", ['status' => 409]);
    }

    $result = wp_update_post(['ID' => $post->ID, 'post_status' => $new], true);
    if (is_wp_error($result)) {
        return $result;
    }

    error_log(sprintf('bcm status change: user=%d post=%d %s->%s', get_current_user_id(), $post->ID, $old, $new));
    return rest_ensure_response(['id' => $post->ID, 'status' => get_post_status($post->ID)]);
}
