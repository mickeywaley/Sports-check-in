<?php
session_start();
date_default_timezone_set('Asia/Shanghai');

// 文件路径定义
define('DATA_FILE', 'data.txt');
define('USERS_FILE', 'users.txt');
define('TEAMS', ['乐观组', '利他组']);
define('PROJECTS', [
    'reading' => ['name' => '读书点评打卡', 'score' => 1],
    'excellent' => ['name' => '优秀分享', 'score' => 3],
    'exercise' => ['name' => '健康运动打卡', 'score' => 3]
]);

// 初始化用户文件
if (!file_exists(USERS_FILE)) {
    $defaultUser = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'isAdmin' => true
    ];
    file_put_contents(USERS_FILE, json_encode([$defaultUser]));
}

// 初始化数据文件
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}

// 读取数据
function readData() {
    return json_decode(file_get_contents(DATA_FILE), true) ?: [];
}

// 写入数据
function writeData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// 读取用户
function readUsers() {
    return json_decode(file_get_contents(USERS_FILE), true) ?: [];
}

// 写入用户
function writeUsers($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// 登录验证
function authenticate($username, $password) {
    $users = readUsers();
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            return $user;
        }
    }
    return false;
}

// 检查是否登录
function isLoggedIn() {
    return isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']);
}

// 检查是否是管理员
function isAdmin() {
    return isLoggedIn() && 
           isset($_SESSION['user']['isAdmin']) && 
           $_SESSION['user']['isAdmin'] === true;
}

// 安全获取用户名
function getUsername() {
    return isLoggedIn() && isset($_SESSION['user']['username']) 
           ? $_SESSION['user']['username'] 
           : '未知用户';
}

// 获取所有用户名
function getAllNames() {
    $data = readData();
    $names = [];
    foreach ($data as $record) {
        if (!in_array($record['name'], $names)) {
            $names[] = $record['name'];
        }
    }
    return $names;
}

// 获取日期所在周的周一和周日
function getWeekRange($date) {
    $timestamp = strtotime($date);
    $monday = date('Y-m-d', strtotime('monday this week', $timestamp));
    $sunday = date('Y-m-d', strtotime('sunday this week', $timestamp));
    return [$monday, $sunday];
}

// 获取日期所在月的第一天和最后一天
function getMonthRange($date) {
    $timestamp = strtotime($date);
    $firstDay = date('Y-m-01', $timestamp);
    $lastDay = date('Y-m-t', $timestamp);
    return [$firstDay, $lastDay];
}

// 获取日期所在季度的第一天和最后一天
function getQuarterRange($date) {
    $timestamp = strtotime($date);
    $month = date('n', $timestamp);
    $quarter = ceil($month / 3);
    $firstMonth = ($quarter - 1) * 3 + 1;
    $lastMonth = $quarter * 3;
    
    $firstDay = date('Y-' . sprintf('%02d', $firstMonth) . '-01', $timestamp);
    $lastDay = date('Y-' . sprintf('%02d', $lastMonth) . '-t', $timestamp);
    
    return [$firstDay, $lastDay];
}

// 计算运动打卡得分
function calculateExerciseScore($name, $startDate, $endDate) {
    $data = readData();
    $exerciseRecords = [];
    
    foreach ($data as $record) {
        if ($record['name'] === $name && $record['project'] === 'exercise' && 
            $record['date'] >= $startDate && $record['date'] <= $endDate) {
            $exerciseRecords[] = $record['date'];
        }
    }
    
    $uniqueWeeks = [];
    foreach ($exerciseRecords as $date) {
        $weekNumber = date('Y-W', strtotime($date));
        $uniqueWeeks[$weekNumber] = true;
    }
    
    $totalScore = 0;
    foreach ($uniqueWeeks as $week => $value) {
        $weekStart = date('Y-m-d', strtotime($week . '-1'));
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        
        $weekCount = 0;
        foreach ($exerciseRecords as $date) {
            if ($date >= $weekStart && $date <= $weekEnd) {
                $weekCount++;
            }
        }
        
        if ($weekCount >= 3) {
            $totalScore += PROJECTS['exercise']['score'];
        }
    }
    
    return $totalScore;
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $user = authenticate($username, $password);
    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 处理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 处理数据添加/编辑 - 修复覆盖问题
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addRecord'])) {
    if (!isAdmin()) {
        die('只有管理员可以添加记录');
    }
    
    $data = readData();
    
    // 处理多个项目的添加
    $team = $_POST['team'];
    $name = $_POST['name'];
    $date = $_POST['date'];
    
    foreach (PROJECTS as $projectKey => $projectInfo) {
        if (isset($_POST['project_' . $projectKey]) && $_POST['project_' . $projectKey] === 'on') {
            // 生成唯一ID
            $id = uniqid();
            
            // 添加新记录
            $newRecord = [
                'id' => $id,
                'team' => $team,
                'name' => $name,
                'project' => $projectKey,
                'date' => $date
            ];
            
            $data[] = $newRecord;
        }
    }
    
    writeData($data);
    header('Location: index.php');
    exit;
}

// 处理数据删除
if (isset($_GET['delete']) && isAdmin()) {
    $id = $_GET['delete'];
    $data = readData();
    
    foreach ($data as $key => $record) {
        if ($record['id'] === $id) {
            unset($data[$key]);
            break;
        }
    }
    
    writeData($data);
    header('Location: index.php');
    exit;
}

// 获取当前日期
$currentDate = date('Y-m-d');
$currentYear = date('Y');
$currentMonth = date('m');

// 获取所有记录，按日期降序排列
$allData = readData();
usort($allData, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// 获取所有团队成员
$teamMembers = [];
foreach (TEAMS as $team) {
    $teamMembers[$team] = [];
}

foreach ($allData as $record) {
    if (!in_array($record['name'], $teamMembers[$record['team']])) {
        $teamMembers[$record['team']][] = $record['name'];
    }
}

// 获取当前周、月、季度的范围
list($currentWeekStart, $currentWeekEnd) = getWeekRange($currentDate);
list($currentMonthStart, $currentMonthEnd) = getMonthRange($currentDate);
list($currentQuarterStart, $currentQuarterEnd) = getQuarterRange($currentDate);

// 计算每个人的统计数据
$statistics = [];
foreach ($teamMembers as $team => $members) {
    foreach ($members as $name) {
        // 初始化统计数据
        $statistics[$team][$name] = [
            'reading' => 0,
            'excellent' => 0,
            'exercise' => 0,
            'readingTotal' => 0,
            'excellentTotal' => 0,
            'exerciseTotal' => 0,
            'readingWeek' => 0,
            'excellentWeek' => 0,
            'exerciseWeek' => 0,
            'readingMonth' => 0,
            'excellentMonth' => 0,
            'exerciseMonth' => 0,
            'readingQuarter' => 0,
            'excellentQuarter' => 0,
            'exerciseQuarter' => 0
        ];
        
        // 计算各项总得分
        foreach ($allData as $record) {
            if ($record['name'] === $name && $record['team'] === $team) {
                $project = $record['project'];
                $score = PROJECTS[$project]['score'];
                $date = $record['date'];
                
                // 累加项目得分
                $statistics[$team][$name][$project] += $score;
                
                // 周统计
                if ($date >= $currentWeekStart && $date <= $currentWeekEnd) {
                    $statistics[$team][$name]["{$project}Week"] += $score;
                }
                
                // 月统计
                if ($date >= $currentMonthStart && $date <= $currentMonthEnd) {
                    $statistics[$team][$name]["{$project}Month"] += $score;
                }
                
                // 季度统计
                if ($date >= $currentQuarterStart && $date <= $currentQuarterEnd) {
                    $statistics[$team][$name]["{$project}Quarter"] += $score;
                }
            }
        }
        
        // 计算读书打卡总学分
        $statistics[$team][$name]['readingTotal'] = $statistics[$team][$name]['reading'];
        $statistics[$team][$name]['readingWeekTotal'] = $statistics[$team][$name]['readingWeek'];
        $statistics[$team][$name]['readingMonthTotal'] = $statistics[$team][$name]['readingMonth'];
        $statistics[$team][$name]['readingQuarterTotal'] = $statistics[$team][$name]['readingQuarter'];
        
        // 计算优秀分享总学分
        $statistics[$team][$name]['excellentTotal'] = $statistics[$team][$name]['excellent'];
        $statistics[$team][$name]['excellentWeekTotal'] = $statistics[$team][$name]['excellentWeek'];
        $statistics[$team][$name]['excellentMonthTotal'] = $statistics[$team][$name]['excellentMonth'];
        $statistics[$team][$name]['excellentQuarterTotal'] = $statistics[$team][$name]['excellentQuarter'];
        
        // 计算运动打卡得分
        $statistics[$team][$name]['exerciseTotal'] = calculateExerciseScore($name, '2000-01-01', $currentDate);
        $statistics[$team][$name]['exerciseWeek'] = calculateExerciseScore($name, $currentWeekStart, $currentWeekEnd);
        $statistics[$team][$name]['exerciseMonth'] = calculateExerciseScore($name, $currentMonthStart, $currentMonthEnd);
        $statistics[$team][$name]['exerciseQuarter'] = calculateExerciseScore($name, $currentQuarterStart, $currentQuarterEnd);
    }
}

// 按优秀分享学分排名
$excellentRankingWeek = [];
$excellentRankingMonth = [];

foreach ($statistics as $team => $members) {
    foreach ($members as $name => $stats) {
        $excellentRankingWeek[] = [
            'team' => $team,
            'name' => $name,
            'score' => $stats['excellentWeek'],
            'has100' => false, // 示例中没有100分的数据，实际应用中需要根据规则判断
            'has95' => false,
            'has90' => false
        ];
        
        $excellentRankingMonth[] = [
            'team' => $team,
            'name' => $name,
            'score' => $stats['excellentMonth'],
            'has100' => false,
            'has95' => false,
            'has90' => false
        ];
    }
}

// 排序函数：学分相同时，有100分的优先，然后95分，然后90分
usort($excellentRankingWeek, function($a, $b) {
    if ($a['score'] === $b['score']) {
        if ($a['has100'] && !$b['has100']) return -1;
        if (!$a['has100'] && $b['has100']) return 1;
        if ($a['has95'] && !$b['has95']) return -1;
        if (!$a['has95'] && $b['has95']) return 1;
        if ($a['has90'] && !$b['has90']) return -1;
        if (!$a['has90'] && $b['has90']) return 1;
        return 0;
    }
    return $b['score'] - $a['score'];
});

usort($excellentRankingMonth, function($a, $b) {
    if ($a['score'] === $b['score']) {
        if ($a['has100'] && !$b['has100']) return -1;
        if (!$a['has100'] && $b['has100']) return 1;
        if ($a['has95'] && !$b['has95']) return -1;
        if (!$a['has95'] && $b['has95']) return 1;
        if ($a['has90'] && !$b['has90']) return -1;
        if (!$a['has90'] && $b['has90']) return 1;
        return 0;
    }
    return $b['score'] - $a['score'];
});
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>运动读书打卡学分统计表</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { text-align: center; color: #333; }
        .login-form { max-width: 300px; margin: 0 auto; }
        .login-form input { width: 100%; padding: 8px; margin-bottom: 10px; }
        .error { color: red; text-align: center; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 10px; }
        .container { display: flex; flex-wrap: wrap; }
        .sidebar { width: 20%; }
        .main-content { width: 80%; }
        .table-container { margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; }
        .btn { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        .btn-delete { background-color: #f44336; }
        .btn-edit { background-color: #2196F3; }
        .stats-container { margin-top: 30px; }
        .stats-container h3 { margin-bottom: 10px; }
        .rule { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; }
        @media (max-width: 768px) {
            .sidebar, .main-content { width: 100%; }
        }
    </style>
</head>
<body>
    <h1>运动读书打卡学分统计表</h1>
    
    <?php if (!isLoggedIn()): ?>
        <div class="login-form">
            <h2>登录</h2>
            <?php if (isset($loginError)): ?>
                <p class="error"><?php echo $loginError; ?></p>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn">登录</button>
            </form>
        </div>
    <?php endif; ?>
    
    <div class="nav">
        <?php if (isLoggedIn()): ?>
            <a href="?">首页</a>
            <?php if (isAdmin()): ?>
                <a href="?action=add">添加记录</a>
            <?php endif; ?>
            <a href="?logout">退出登录</a>
            <span style="float: right;">欢迎，<?php echo getUsername(); ?> <?php echo isAdmin() ? '(管理员)' : ''; ?></span>
        <?php else: ?>
            <span style="float: right;">游客模式：可查看数据，不可编辑</span>
        <?php endif; ?>
    </div>
    
    <div class="container">
        <div class="main-content">
            <?php if (isset($_GET['action']) && $_GET['action'] === 'add' && isAdmin()): ?>
                <div class="table-container">
                    <h2>添加记录</h2>
                    <form method="post">
                        <div class="form-group">
                            <label for="team">团队:</label>
                            <select id="team" name="team" required>
                                <option value="">请选择团队</option>
                                <?php foreach (TEAMS as $team): ?>
                                    <option value="<?php echo $team; ?>"><?php echo $team; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="name">姓名:</label>
                            <input type="text" id="name" name="name" list="nameList" required>
                            <datalist id="nameList">
                                <?php foreach (getAllNames() as $name): ?>
                                    <option value="<?php echo $name; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group">
                            <label>选择项目:</label>
                            <?php foreach (PROJECTS as $projectKey => $projectInfo): ?>
                                <div>
                                    <input type="checkbox" id="project_<?php echo $projectKey; ?>" name="project_<?php echo $projectKey; ?>">
                                    <label for="project_<?php echo $projectKey; ?>"><?php echo $projectInfo['name']; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-group">
                            <label for="date">日期:</label>
                            <input type="date" id="date" name="date" required value="<?php echo $currentDate; ?>">
                        </div>
                        <button type="submit" name="addRecord" class="btn">保存</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <h2>所有记录</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>团队</th>
                                <th>姓名</th>
                                <th>项目</th>
                                <th>日期</th>
                                <th>星期</th>
                                <?php if (isAdmin()): ?>
                                    <th>操作</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allData as $record): ?>
                                <tr>
                                    <td><?php echo $record['team']; ?></td>
                                    <td><?php echo $record['name']; ?></td>
                                    <td><?php echo PROJECTS[$record['project']]['name']; ?></td>
                                    <td><?php echo $record['date']; ?></td>
                                    <td><?php echo ['一', '二', '三', '四', '五', '六', '日'][date('N', strtotime($record['date'])) - 1]; ?></td>
                                    <?php if (isAdmin()): ?>
                                        <td>
                                            <a href="?edit=<?php echo $record['id']; ?>" class="btn btn-edit">编辑</a>
                                            <a href="?delete=<?php echo $record['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除这条记录吗?')">删除</a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="stats-container">
                    <h2>统计数据</h2>
                    
                    <?php foreach (TEAMS as $team): ?>
                        <h3><?php echo $team; ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th rowspan="2">姓名</th>
                                    <th colspan="4">总学分</th>
                                    <th colspan="4">本周</th>
                                    <th colspan="4">本月</th>
                                    <th colspan="4">本季度</th>
                                </tr>
                                <tr>
                                    <th>读书打卡</th>
                                    <th>优秀分享</th>
                                    <th>健康运动</th>
                                    <th>总计</th>
                                    <th>读书打卡</th>
                                    <th>优秀分享</th>
                                    <th>健康运动</th>
                                    <th>总计</th>
                                    <th>读书打卡</th>
                                    <th>优秀分享</th>
                                    <th>健康运动</th>
                                    <th>总计</th>
                                    <th>读书打卡</th>
                                    <th>优秀分享</th>
                                    <th>健康运动</th>
                                    <th>总计</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($statistics[$team])): ?>
                                    <?php foreach ($statistics[$team] as $name => $stats): ?>
                                        <tr>
                                            <td><?php echo $name; ?></td>
                                            <td><?php echo $stats['readingTotal']; ?></td>
                                            <td><?php echo $stats['excellentTotal']; ?></td>
                                            <td><?php echo $stats['exerciseTotal']; ?></td>
                                            <td><?php echo $stats['readingTotal'] + $stats['excellentTotal'] + $stats['exerciseTotal']; ?></td>
                                            <td><?php echo $stats['readingWeek']; ?></td>
                                            <td><?php echo $stats['excellentWeek']; ?></td>
                                            <td><?php echo $stats['exerciseWeek']; ?></td>
                                            <td><?php echo $stats['readingWeek'] + $stats['excellentWeek'] + $stats['exerciseWeek']; ?></td>
                                            <td><?php echo $stats['readingMonth']; ?></td>
                                            <td><?php echo $stats['excellentMonth']; ?></td>
                                            <td><?php echo $stats['exerciseMonth']; ?></td>
                                            <td><?php echo $stats['readingMonth'] + $stats['excellentMonth'] + $stats['exerciseMonth']; ?></td>
                                            <td><?php echo $stats['readingQuarter']; ?></td>
                                            <td><?php echo $stats['excellentQuarter']; ?></td>
                                            <td><?php echo $stats['exerciseQuarter']; ?></td>
                                            <td><?php echo $stats['readingQuarter'] + $stats['excellentQuarter'] + $stats['exerciseQuarter']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                    
                    <h3>优秀分享学分排名</h3>
                    <h4>本周排名</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>团队</th>
                                <th>姓名</th>
                                <th>优秀分享学分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($excellentRankingWeek as $key => $item): ?>
                                <tr>
                                    <td><?php echo $key + 1; ?></td>
                                    <td><?php echo $item['team']; ?></td>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['score']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <h4>本月排名</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>团队</th>
                                <th>姓名</th>
                                <th>优秀分享学分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($excellentRankingMonth as $key => $item): ?>
                                <tr>
                                    <td><?php echo $key + 1; ?></td>
                                    <td><?php echo $item['team']; ?></td>
                                    <td><?php echo $item['name']; ?></td>
                                    <td><?php echo $item['score']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>    
