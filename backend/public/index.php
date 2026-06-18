<?php
session_start();
require __DIR__ . '/../app/helpers.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    if ($path === '/' || $path === '/login') {
        student_login();
    } elseif ($path === '/logout') {
        session_destroy();
        redirect_to('/login');
    } elseif ($path === '/dashboard') {
        student_dashboard();
    } elseif ($path === '/unlock') {
        unlock_page();
    } elseif ($path === '/progress/save') {
        save_progress();
    } elseif ($path === '/admin/login') {
        admin_login();
    } elseif ($path === '/admin/logout') {
        unset($_SESSION['admin_id']);
        redirect_to('/admin/login');
    } elseif ($path === '/admin') {
        admin_home();
    } elseif ($path === '/admin/users') {
        admin_users();
    } elseif ($path === '/admin/users/create') {
        admin_user_form();
    } elseif (preg_match('~^/admin/users/(\d+)$~', $path, $m)) {
        admin_user_detail((int) $m[1]);
    } elseif ($path === '/admin/codes') {
        admin_codes();
    } elseif ($path === '/admin/codes/create') {
        admin_create_code();
    } elseif (substr($path, 0, strlen('/learn/')) === '/learn/') {
        serve_learning_content(substr($path, strlen('/learn/')));
    } else {
        http_response_code(404);
        echo '页面不存在。';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '数据库还没有准备好。请先访问 /install.php 初始化，或检查数据库账号密码。';
}

function student_login(): void
{
    if (current_user()) {
        redirect_to('/dashboard');
    }
    $error = '';
    if (is_post()) {
        verify_csrf();
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt = db()->prepare('select * from users where phone = ? and status = "active"');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            record_login((int) $user['id']);
            redirect_to('/dashboard');
        }
        $error = '手机号或密码不对，再检查一下。';
    }

    render_header('学生登录');
    ?>
    <div class="topbar">
      <div>
        <div class="brand">雏鹰计划学习入口</div>
        <p class="muted">登录后会读取你的版本、开始日期和学习进度。换手机、换电脑，也能接着学。</p>
      </div>
      <a class="btn" href="/admin/login">后台入口</a>
    </div>
    <?php if ($error): ?><div class="notice danger"><?= h($error) ?></div><?php endif; ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <div class="field"><label>手机号</label><input name="phone" autocomplete="username"></div>
      <div class="field"><label>密码</label><input name="password" type="password" autocomplete="current-password"></div>
      <button class="btn primary" type="submit">进入我的45天计划</button>
    </form>
    <?php
    render_footer();
}

function student_dashboard(): void
{
    $user = require_user();
    $today = suggested_day($user);
    $entitledDays = user_max_day($user);
    $availableDay = available_day($user);
    $progress = progress_stats((int) $user['id']);
    $day = str_pad((string) $today, 2, '0', STR_PAD_LEFT);

    render_header('我的45天计划');
    ?>
    <div class="topbar">
      <div>
        <div class="brand"><?= h($user['nickname']) ?>，今天也算数。</div>
        <p class="muted">当前版本：<?= $user['access_type'] === 'full' ? '完整版' : '体验版' ?>。今天先照着 Day <?= $today ?> 执行就好。</p>
      </div>
      <div class="actions">
        <?php if ($user['access_type'] !== 'full'): ?><a class="btn gold" href="/unlock">输入解锁码</a><?php endif; ?>
        <a class="btn" href="/logout">退出</a>
      </div>
    </div>
    <section class="card hero">
      <h2>继续 Day <?= $today ?></h2>
      <p>别着急，先稳住节奏。背单词、学课程、做习题、日测，一天只清这一格。</p>
      <div class="actions">
        <a class="btn gold" href="/learn/daily-tasks/day-<?= $day ?>.html">开始今日完整任务</a>
        <a class="btn" href="/learn/index.html">进入系统主页</a>
      </div>
    </section>
    <section class="grid grid-4">
      <div class="stat"><strong><?= h((string) $progress['completed_days']) ?> / 45</strong><span>完成天数</span></div>
      <div class="stat"><strong><?= h((string) $user['login_count']) ?></strong><span>登录次数</span></div>
      <div class="stat"><strong><?= h((string) $availableDay) ?></strong><span>今天已开放到 Day</span></div>
      <div class="stat"><strong><?= h((string) $entitledDays) ?></strong><span>账号权益天数</span></div>
    </section>
    <section class="card">
      <h2>学习入口</h2>
      <div class="grid grid-4">
        <a class="btn" href="/learn/eagle-vocab-handbook.html">单词日历</a>
        <a class="btn" href="/learn/eagle-lecture-calendar.html">讲义日历</a>
        <a class="btn" href="/learn/eagle-workbook-breakdown.html">习题日历</a>
        <a class="btn" href="/learn/eagle-test-pack.html">测试日历</a>
      </div>
    </section>
    <?php
    render_footer();
}

function unlock_page(): void
{
    $user = require_user();
    $message = '';
    if (is_post()) {
        verify_csrf();
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $stmt = db()->prepare('select * from unlock_codes where code = ? and used_by_user_id is null and (expires_at is null or expires_at > now())');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) {
            $message = '这个解锁码暂时不能使用，请检查后再试。';
        } elseif ($row['assigned_phone'] && $row['assigned_phone'] !== $user['phone']) {
            $message = '这个解锁码不是发给当前手机号的。';
        } else {
            db()->beginTransaction();
            db()->prepare('update users set access_type = "full" where id = ?')->execute([$user['id']]);
            db()->prepare('update unlock_codes set used_by_user_id = ?, used_at = now() where id = ?')->execute([$user['id'], $row['id']]);
            db()->commit();
            $message = '完整版已解锁。你的学习进度会接着走，不会从头来。';
        }
    }

    render_header('解锁完整版');
    ?>
    <div class="topbar">
      <div><div class="brand">解锁完整版</div><p class="muted">体验版学到 Day3 后，输入老师发你的解锁码，就能继续 Day4-Day45。</p></div>
      <a class="btn" href="/dashboard">返回学习入口</a>
    </div>
    <?php if ($message): ?><div class="notice"><?= h($message) ?></div><?php endif; ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <div class="field"><label>解锁码</label><input name="code" placeholder="输入老师发你的解锁码"></div>
      <button class="btn primary" type="submit">解锁并继续学习</button>
    </form>
    <?php
    render_footer();
}

function serve_learning_content(string $relative): void
{
    $user = require_user();
    $relative = str_replace(['..', '\\'], ['', '/'], urldecode($relative));
    $relative = ltrim($relative, '/');
    if ($relative === '') {
        $relative = 'index.html';
    }
    if ($relative === 'eagle-paywall.html') {
        redirect_to('/unlock');
    }

    $day = day_from_path($relative);
    if ($day && !can_visit_day($user, $day)) {
        render_header('内容待解锁');
        ?>
        <section class="card hero">
          <h2>后面的内容先替你收好啦</h2>
          <p>课程会按开营日期逐日开放。体验版最多开放 Day1-Day3，完整版最多开放 Day1-Day45。</p>
          <div class="actions">
            <a class="btn gold" href="/unlock">输入解锁码</a>
            <a class="btn" href="/dashboard">返回我的学习入口</a>
          </div>
        </section>
        <?php
        render_footer();
        return;
    }

    if ($day) {
        touch_progress((int) $user['id'], $day);
    }

    $root = realpath(config_value('content_path'));
    $file = realpath($root . DIRECTORY_SEPARATOR . $relative);
    if (!$root || !$file || substr($file, 0, strlen($root)) !== $root || !is_file($file)) {
        http_response_code(404);
        echo '学习内容不存在。';
        return;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip' => 'application/zip',
    ];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    if ($ext !== 'html') {
        readfile($file);
        return;
    }

    $html = file_get_contents($file);
    $bridgeConfig = [
        'save' => '/progress/save',
        'accessType' => $user['access_type'],
        'maxDay' => available_day($user),
        'entitledDays' => user_max_day($user),
        'todayDay' => suggested_day($user),
        'unlockUrl' => '/unlock',
    ];
    $bridgeConfigScript = '<script>window.EAGLE_BACKEND=' . json_encode($bridgeConfig, JSON_UNESCAPED_UNICODE) . ';</script>';
    $bridge = '<script src="/learn/backend-bridge.js"></script>';
    if (stripos($html, '</head>') !== false) {
        $html = preg_replace('~</head>~i', $bridgeConfigScript . '</head>', $html, 1);
    } else {
        $html = $bridgeConfigScript . $html;
    }
    if (stripos($html, '</body>') !== false) {
        $html = preg_replace('~</body>~i', $bridge . '</body>', $html, 1);
    } else {
        $html .= $bridge;
    }
    echo $html;
}

function save_progress(): void
{
    $user = require_user();
    $day = max(1, min(45, (int) ($_POST['day'] ?? $_GET['day'] ?? 1)));
    $task = $_POST['task'] ?? $_GET['task'] ?? '';
    $map = [
        'vocab' => 'task_vocab',
        'lecture' => 'task_lecture',
        'workbook' => 'task_workbook',
        'test' => 'task_test',
    ];
    if (!isset($map[$task])) {
        http_response_code(422);
        echo '任务类型不正确。';
        return;
    }
    if (!can_visit_day($user, $day)) {
        http_response_code(403);
        echo '这一天还没有开放。';
        return;
    }
    touch_progress((int) $user['id'], $day);
    db()->prepare('update study_progress set ' . $map[$task] . ' = 1 where user_id = ? and day_no = ?')->execute([$user['id'], $day]);
    db()->prepare('update study_progress set completed_at = if(task_vocab=1 and task_lecture=1 and task_workbook=1 and task_test=1, coalesce(completed_at, now()), completed_at) where user_id = ? and day_no = ?')->execute([$user['id'], $day]);
    echo 'ok';
}

function touch_progress(int $userId, int $day): void
{
    $stmt = db()->prepare('insert into study_progress (user_id, day_no, first_opened_at) values (?, ?, now()) on duplicate key update first_opened_at = coalesce(first_opened_at, values(first_opened_at))');
    $stmt->execute([$userId, $day]);
}

function suggested_day(array $user): int
{
    return available_day($user);
}

function progress_stats(int $userId): array
{
    $stmt = db()->prepare('select count(*) as completed_days from study_progress where user_id = ? and completed_at is not null');
    $stmt->execute([$userId]);
    return ['completed_days' => (int) ($stmt->fetch()['completed_days'] ?? 0)];
}

function admin_login(): void
{
    if (current_admin()) {
        redirect_to('/admin');
    }
    $error = '';
    if (is_post()) {
        verify_csrf();
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt = db()->prepare('select * from admins where phone = ?');
        $stmt->execute([$phone]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            redirect_to('/admin');
        }
        $error = '后台账号或密码不对。';
    }

    render_header('后台登录');
    ?>
    <div class="topbar">
      <div><div class="brand">雏鹰计划后台</div><p class="muted">用来添加学生账号、发放解锁码、查看学习进度。</p></div>
      <a class="btn" href="/login">学生入口</a>
    </div>
    <?php if ($error): ?><div class="notice danger"><?= h($error) ?></div><?php endif; ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <div class="field"><label>手机号</label><input name="phone" autocomplete="username"></div>
      <div class="field"><label>密码</label><input name="password" type="password" autocomplete="current-password"></div>
      <button class="btn primary" type="submit">进入后台</button>
    </form>
    <?php
    render_footer();
}

function admin_home(): void
{
    require_admin();
    $users = db()->query('select count(*) as c from users')->fetch()['c'] ?? 0;
    $trial = db()->query('select count(*) as c from users where access_type = "trial"')->fetch()['c'] ?? 0;
    $full = db()->query('select count(*) as c from users where access_type = "full"')->fetch()['c'] ?? 0;
    $logins = db()->query('select coalesce(sum(login_count),0) as c from users')->fetch()['c'] ?? 0;
    render_header('后台首页');
    ?>
    <div class="topbar">
      <div><div class="brand">后台总览</div><p class="muted">账号、权限、进度都在这里看。先把学生账号建好，学生就能登录学习。</p></div>
      <div class="actions"><a class="btn" href="/admin/logout">退出后台</a></div>
    </div>
    <section class="grid grid-4">
      <div class="stat"><strong><?= h((string) $users) ?></strong><span>学生账号</span></div>
      <div class="stat"><strong><?= h((string) $trial) ?></strong><span>体验版</span></div>
      <div class="stat"><strong><?= h((string) $full) ?></strong><span>完整版</span></div>
      <div class="stat"><strong><?= h((string) $logins) ?></strong><span>累计登录</span></div>
    </section>
    <section class="card">
      <h2>常用操作</h2>
      <div class="grid grid-4">
        <a class="btn primary" href="/admin/users/create">添加学生账号</a>
        <a class="btn" href="/admin/users">学生列表</a>
        <a class="btn gold" href="/admin/codes/create">生成解锁码</a>
        <a class="btn" href="/admin/codes">解锁码列表</a>
      </div>
    </section>
    <?php
    render_footer();
}

function admin_users(): void
{
    require_admin();
    $rows = db()->query('select * from users order by id desc')->fetchAll();
    render_header('学生列表');
    ?>
    <div class="topbar">
      <div><div class="brand">学生账号</div><p class="muted">下单后，在这里添加手机号和学习版本。</p></div>
      <div class="actions"><a class="btn primary" href="/admin/users/create">添加账号</a><a class="btn" href="/admin">返回后台</a></div>
    </div>
    <div class="card table-wrap">
      <table>
        <thead><tr><th>昵称</th><th>手机号</th><th>版本</th><th>开始日期</th><th>登录次数</th><th>最近登录</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= h($row['nickname']) ?></td>
            <td><?= h($row['phone']) ?></td>
            <td><?= $row['access_type'] === 'full' ? '完整版' : '体验版' ?></td>
            <td><?= h($row['start_date']) ?></td>
            <td><?= h((string) $row['login_count']) ?></td>
            <td><?= h($row['last_login_at'] ?: '-') ?></td>
            <td><a class="btn small" href="/admin/users/<?= (int) $row['id'] ?>">查看</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    render_footer();
}

function admin_user_form(): void
{
    require_admin();
    $message = '';
    if (is_post()) {
        verify_csrf();
        $nickname = trim($_POST['nickname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $access = $_POST['access_type'] === 'full' ? 'full' : 'trial';
        $startDate = $_POST['start_date'] ?: date('Y-m-d');
        if ($nickname === '' || $phone === '' || $password === '') {
            $message = '昵称、手机号、密码都要填写。';
        } else {
            $stmt = db()->prepare('insert into users (nickname, phone, password_hash, access_type, start_date) values (?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$nickname, $phone, password_hash($password, PASSWORD_DEFAULT), $access, $startDate]);
                redirect_to('/admin/users');
            } catch (PDOException $e) {
                $message = '这个手机号已经存在。';
            }
        }
    }

    render_header('添加学生账号');
    ?>
    <div class="topbar">
      <div><div class="brand">添加学生账号</div><p class="muted">下单后手动录入手机号。体验版默认只开放 Day1-Day3。</p></div>
      <a class="btn" href="/admin/users">返回列表</a>
    </div>
    <?php if ($message): ?><div class="notice danger"><?= h($message) ?></div><?php endif; ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <div class="field"><label>学生昵称</label><input name="nickname" placeholder="例如：小丹"></div>
      <div class="field"><label>手机号</label><input name="phone"></div>
      <div class="field"><label>初始密码</label><input name="password" placeholder="建议用手机号后6位或单独告知学生"></div>
      <div class="field"><label>解锁版本</label><select name="access_type"><option value="trial">0.1元体验版：开放 Day1-Day3</option><option value="full">29.9完整版：开放 Day1-Day45</option></select></div>
      <div class="field"><label>开始日期</label><input name="start_date" type="date" value="<?= h(date('Y-m-d')) ?>"></div>
      <button class="btn primary" type="submit">创建账号</button>
    </form>
    <?php
    render_footer();
}

function admin_user_detail(int $id): void
{
    require_admin();
    $stmt = db()->prepare('select * from users where id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo '学生不存在。';
        return;
    }
    $progress = db()->prepare('select * from study_progress where user_id = ? order by day_no');
    $progress->execute([$id]);
    $rows = $progress->fetchAll();

    render_header('学生进度');
    ?>
    <div class="topbar">
      <div><div class="brand"><?= h($user['nickname']) ?> 的学习进度</div><p class="muted"><?= h($user['phone']) ?> · <?= $user['access_type'] === 'full' ? '完整版' : '体验版' ?> · 登录 <?= h((string) $user['login_count']) ?> 次</p></div>
      <a class="btn" href="/admin/users">返回学生列表</a>
    </div>
    <div class="card table-wrap">
      <table>
        <thead><tr><th>Day</th><th>单词</th><th>讲义</th><th>习题</th><th>日测</th><th>完成时间</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td>Day <?= (int) $row['day_no'] ?></td>
            <td><?= $row['task_vocab'] ? '已完成' : '-' ?></td>
            <td><?= $row['task_lecture'] ? '已完成' : '-' ?></td>
            <td><?= $row['task_workbook'] ? '已完成' : '-' ?></td>
            <td><?= $row['task_test'] ? '已完成' : '-' ?></td>
            <td><?= h($row['completed_at'] ?: '-') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    render_footer();
}

function admin_codes(): void
{
    require_admin();
    $rows = db()->query('select c.*, u.nickname as used_name from unlock_codes c left join users u on u.id = c.used_by_user_id order by c.id desc limit 200')->fetchAll();
    render_header('解锁码列表');
    ?>
    <div class="topbar">
      <div><div class="brand">解锁码</div><p class="muted">体验版学生付款升级后，把未使用的解锁码发给他即可。</p></div>
      <div class="actions"><a class="btn primary" href="/admin/codes/create">生成解锁码</a><a class="btn" href="/admin">返回后台</a></div>
    </div>
    <div class="card table-wrap">
      <table>
        <thead><tr><th>解锁码</th><th>绑定手机号</th><th>使用状态</th><th>过期时间</th><th>创建时间</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><strong><?= h($row['code']) ?></strong></td>
            <td><?= h($row['assigned_phone'] ?: '不限') ?></td>
            <td><?= $row['used_by_user_id'] ? '已被 ' . h($row['used_name']) . ' 使用' : '未使用' ?></td>
            <td><?= h($row['expires_at'] ?: '-') ?></td>
            <td><?= h($row['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    render_footer();
}

function admin_create_code(): void
{
    require_admin();
    $created = '';
    if (is_post()) {
        verify_csrf();
        $phone = trim($_POST['assigned_phone'] ?? '');
        $expires = trim($_POST['expires_at'] ?? '') ?: null;
        if ($expires !== null) {
            $expires = str_replace('T', ' ', $expires);
            if (strlen($expires) === 16) {
                $expires .= ':00';
            }
        }
        $created = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        db()->prepare('insert into unlock_codes (code, assigned_phone, expires_at) values (?, ?, ?)')->execute([$created, $phone ?: null, $expires]);
    }

    render_header('生成解锁码');
    ?>
    <div class="topbar">
      <div><div class="brand">生成解锁码</div><p class="muted">用于把体验版升级为完整版，进度会自动衔接。</p></div>
      <a class="btn" href="/admin/codes">返回解锁码列表</a>
    </div>
    <?php if ($created): ?><div class="notice">新解锁码：<strong><?= h($created) ?></strong></div><?php endif; ?>
    <form class="card" method="post">
      <?= csrf_field() ?>
      <div class="field"><label>绑定手机号，可不填</label><input name="assigned_phone" placeholder="填写后只有这个手机号能用"></div>
      <div class="field"><label>过期时间，可不填</label><input name="expires_at" type="datetime-local"></div>
      <button class="btn gold" type="submit">生成解锁码</button>
    </form>
    <?php
    render_footer();
}
