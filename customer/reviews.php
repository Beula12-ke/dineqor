<?php require_once __DIR__ . '/../includes/page.php';
$u = page_guard(['customer']);
$q = db()->prepare("SELECT o.id AS order_id,o.order_number,o.status,o.created_at,r.name AS restaurant_name,rv.id AS review_id,rv.rating,rv.title,rv.comment
  FROM orders o JOIN restaurants r ON r.id=o.restaurant_id
  LEFT JOIN reviews rv ON rv.order_id=o.id AND rv.customer_user_id=o.customer_user_id
  WHERE o.customer_user_id=? AND o.status IN ('completed','delivered')
  ORDER BY o.created_at DESC LIMIT 100");
$q->execute([(int)$u['id']]); $orders = $q->fetchAll();
page_head('My reviews'); nav_bar($u); ?>
<main class="customer-home customer-workspace customer-list-page">
  <header class="customer-heading"><div><div class="eyebrow">YOUR ACCOUNT</div><h1>My reviews</h1><p>Share feedback on orders you’ve completed or received.</p></div><a class="customer-browse" href="<?= e(url()) ?>">Browse restaurants <span>→</span></a></header>
  <?php if ($orders): ?><div class="customer-review-list"><?php foreach ($orders as $order): ?>
    <section class="profile-card customer-review-card" id="review-order-<?= (int)$order['order_id'] ?>"><div class="review-order-heading"><div><div class="eyebrow">ORDER #<?= e($order['order_number']) ?> · <?= e(date('M j, Y', strtotime($order['created_at']))) ?></div><h2><?= e($order['restaurant_name']) ?></h2></div><span class="review-complete">✓ <?= e(ucfirst($order['status'])) ?></span></div>
      <?php if ($order['review_id']): ?><div class="customer-review-readonly"><div class="customer-review-rating" aria-label="<?= (int)$order['rating'] ?> out of 5 stars"><?= str_repeat('★', (int)$order['rating']) ?><?= str_repeat('☆', 5 - (int)$order['rating']) ?><span><?= (int)$order['rating'] ?> / 5</span></div><?php if ($order['title']): ?><h3><?= e($order['title']) ?></h3><?php endif ?><?php if ($order['comment']): ?><p><?= nl2br(e($order['comment'])) ?></p><?php endif ?><small>Your review has been submitted.</small></div>
      <?php else: ?><form class="customer-review-form" data-order-id="<?= (int)$order['order_id'] ?>">
        <div class="setting-field"><label for="rating-<?= (int)$order['order_id'] ?>">Your rating</label><select id="rating-<?= (int)$order['order_id'] ?>" name="rating" required><option value="">Select a rating</option><?php for ($star=5; $star>=1; $star--): ?><option value="<?= $star ?>" <?= (int)$order['rating'] === $star ? 'selected' : '' ?>><?= str_repeat('★',$star) ?><?= str_repeat('☆',5-$star) ?> · <?= $star ?> <?= $star === 1 ? 'star' : 'stars' ?></option><?php endfor ?></select><div class="err" data-err="rating"></div></div>
        <div class="setting-field"><label for="review-title-<?= (int)$order['order_id'] ?>">Title <span class="optional-label">Optional</span></label><input id="review-title-<?= (int)$order['order_id'] ?>" name="title" maxlength="150" value="<?= e($order['title'] ?? '') ?>" placeholder="Summarise your experience"><div class="err" data-err="title"></div></div>
        <div class="setting-field profile-wide"><label for="review-comment-<?= (int)$order['order_id'] ?>">Your review</label><textarea id="review-comment-<?= (int)$order['order_id'] ?>" name="comment" rows="3" maxlength="1200" placeholder="What did you enjoy? What could be better?"><?= e($order['comment'] ?? '') ?></textarea><div class="err" data-err="comment"></div></div>
        <div class="review-form-actions"><div class="msg" data-review-message role="status"></div><button type="submit" class="btn sm">Share review</button></div>
      </form><?php endif ?>
    </section>
  <?php endforeach ?></div><?php else: ?><section class="dash-panel"><div class="dash-empty"><span>★</span><b>No completed orders to review yet</b><p>After an order has been delivered or completed, you can leave feedback here.</p><a class="panel-link" href="<?= e(url('customer/orders.php')) ?>">View my orders →</a></div></section><?php endif ?>
</main>
<?php page_foot('reviews.js');
