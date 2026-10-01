<?php
require_once __DIR__ . '/../../includes/auth.php';
json_out(['ok' => true, 'csrf' => csrf_token()]);
