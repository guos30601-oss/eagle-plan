<?php
session_start();
require __DIR__ . '/../app/helpers.php';

$message = '';
$ok = false;

if (is_post()) {
    verify_csrf();
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        db()->exec($statement);
    }

    $name = trim($_POST['name'] ?? '超管');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($phone === '' || $password === '') {
        $message = '请填写超管手机号和登录密码。';
    } else {
        $stmt = db()->prepare('insert into admins (name, phone, password_hash, role) values (?, ?, ?, "super")
            on duplicate key update name = values(name), password_hash = values(password_hash)');
        $stmt->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        $message = '安装完成。现在可以进入后台登录了。';
        $ok = true;
    }
}

render_header('安装雏鹰计划后台');
?>
<div class="topbar">
  <div>
    <div class="brand">雏鹰计划后台安装</div>
    <p class="muted">第一次部署时使用。创建超管后，建议把 install.php 改名或删除。</p>
  </div>
  <a class="btn" href="/admin/login">进入后台登录</a>
</div>
<?php if ($message): ?><div class="notice <?= $ok ? '' : 'danger' ?>"><?= h($message) ?></div><?php endif; ?>
<form class="card" method="post">
  <?= csrf_field() ?>
  <div class="field"><label>超管昵称</label><input name="name" value="超管"></div>
  <div class="field"><label>超管手机号</label><input name="phone" placeholder="用于后台登录"></div>
  <div class="field"><label>登录密码</label><input name="password" type="password" placeholder="请设置一个好记但安全的密码"></div>
  <button class="btn primary" type="submit">创建超管并初始化数据库</button>
</form>
<?php render_footer(); ?>
