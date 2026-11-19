<?php
if (!class_exists('NextendSocialLogin')) { throw new RuntimeException('Activate Nextend Social Login first.'); }
$page = get_page_by_path('connect', OBJECT, 'page');
if ($page && $page->post_status !== 'publish') { throw new RuntimeException('The existing connect page must be reviewed before use.'); }
$id = $page ? $page->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Sign in', 'post_name' => 'connect', 'post_content' => '[nextend_social_login provider="google"]'], true);
if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
NextendSocialLogin::$settings->set('proxy-page', (int) $id);
echo 'GOOGLE_CALLBACK=' . add_query_arg('loginSocial', 'google', get_permalink($id)) . PHP_EOL;
