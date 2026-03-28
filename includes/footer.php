<?php
/**
 * 公共页脚模板
 */
if (!isset($siteName)) {
    require_once __DIR__ . '/functions.php';
    $siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
}
$copyright = getSiteSetting('copyright', 'Copyright © ' . date('Y') . ' TRPG Bot 导航. All Rights Reserved.');
$icpNumber = getSiteSetting('icp_number', '');
$icpLink   = getSiteSetting('icp_link', 'https://beian.miit.gov.cn/');
$friendLinks = getFriendLinks();
?>
</main>

<footer>
  <?php if ($friendLinks): ?>
  <div class="footer-links-wrap">
    <div class="footer-links-inner">
      <div class="footer-links-list">
        <?php foreach ($friendLinks as $link): ?>
        <a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer" class="footer-link-item">
          <?php if ($link['logo']): ?>
          <img src="<?= e($link['logo']) ?>" alt="<?= e($link['name']) ?>" class="footer-link-logo">
          <?php endif; ?>
          <span><?= e($link['name']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="footer-inner">
    <div class="footer-copyright"><?= nl2br(e($copyright)) ?></div>
    <?php if ($icpNumber): ?>
    <div class="footer-icp">
      <a href="<?= e($icpLink) ?>" target="_blank" rel="noopener"><?= e($icpNumber) ?></a>
    </div>
    <?php endif; ?>
  </div>
</footer>

</div><!-- .page-wrapper -->

<script src="/assets/js/main.js"></script>
</body>
</html>
