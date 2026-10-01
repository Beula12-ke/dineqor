<?php
// Daraja credentials belong here on the server only. Never put them in browser code or commit real keys.
// Create an M-Pesa Express (STK Push) app in the Daraja portal and fill these values for sandbox testing.
define('MPESA_ENV', 'sandbox'); // keep sandbox until live credentials and callback hosting are ready
define('MPESA_CONSUMER_KEY', '');
define('MPESA_CONSUMER_SECRET', '');
define('MPESA_SHORTCODE', ''); // shortcode supplied for your Daraja app
define('MPESA_PASSKEY', '');
define('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'); // Till: CustomerBuyGoodsOnline
define('MPESA_CALLBACK_TOKEN', 'replace-with-a-long-random-secret');
define('MPESA_CALLBACK_URL', ''); // absolute, publicly reachable callback URL (HTTPS for production)
