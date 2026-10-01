<?php
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/public-layout.php';
page_head('Contact Dineqor');
public_nav('contact');
$supportEmail = platform_setting('support_email');
$supportPhone = platform_setting('support_phone');
?>
<main class="public-content-page">
  <section class="content-page-hero"><div class="wrap"><div class="eyebrow">WE’RE HERE TO HELP</div><h1>Let’s talk about <em>good food.</em></h1><p>Reach out to the Dineqor team or learn how to bring your restaurant onto the platform.</p></div></section>
  <section class="wrap contact-content content-page-body"><article class="contact-card"><span class="step-icon" aria-hidden="true">✉</span><h2>Customer and platform support</h2><p>Use the support contact details below to get in touch.</p>
    <?php if ($supportEmail): ?><a class="contact-detail" href="mailto:<?= e($supportEmail) ?>"><small>Email</small><b><?= e($supportEmail) ?></b></a><?php endif ?>
    <?php if ($supportPhone): ?><a class="contact-detail" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>"><small>Phone</small><b><?= e($supportPhone) ?></b></a><?php endif ?>
    <?php if (!$supportEmail && !$supportPhone): ?><p class="contact-empty">Support contact details will appear here when configured by the Dineqor administrator.</p><?php endif ?>
  </article><article class="contact-card contact-partner"><span class="step-icon" aria-hidden="true">✳</span><h2>Restaurant partnership</h2><p>Interested in listing your restaurant? Start a partner application and our team will review it.</p><a class="partner-button" href="<?= e(url('register-restaurant.php')) ?>">Partner with Dineqor <span>→</span></a></article></section>
</main>
<?php public_footer(); page_foot(); ?>
