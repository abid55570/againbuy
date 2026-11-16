<?php
$settings = get_option('advmo_settings', []);
$settings = is_array($settings) ? $settings : [];
$settings['cloud_provider'] = 'minio';
update_option('advmo_settings', $settings);
echo "S3-compatible media provider selected.\n";
