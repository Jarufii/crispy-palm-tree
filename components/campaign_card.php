<?php foreach($campaigns as $c): ?>
<div class="col-md-4">
    <div class="card shadow-sm">
        <img src="<?= $c['image'] ?>" class="card-img-top">
        <div class="card-body">
            <h6><?= $c['title'] ?></h6>
            <p><?= $c['description'] ?></p>

            <div class="progress mb-2">
                <div class="progress-bar" style="width: <?= $c['progress'] ?>%"></div>
            </div>

            <small>₱<?= $c['current_amount'] ?> / ₱<?= $c['target_amount'] ?></small>

            <button class="btn btn-primary w-100 mt-2 donateBtn" data-id="<?= $c['campaign_id'] ?>">
                Donate
            </button>
        </div>
    </div>
</div>
<?php endforeach; ?>