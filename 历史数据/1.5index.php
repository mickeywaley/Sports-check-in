<?php
// 运动读书打卡学分统计表
// 配置信息
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('DATA_FILE', 'data.txt');
define('USERS_FILE', 'users.txt');

// 初始化文件
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([]));
}

// 会话管理
session_start();

// 验证管理员
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// 登录处理
if (isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USERNAME && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 登出处理
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 读取数据
$data = json_decode(file_get_contents(DATA_FILE), true);
$users = json_decode(file_get_contents(USERS_FILE), true);

// 添加/编辑数据
if (isset($_POST['submit'])) {
    $id = isset($_POST['id']) ? $_POST['id'] : uniqid();
    $team = $_POST['team'];
    $name = !empty($_POST['new_name']) ? $_POST['new_name'] : $_POST['name'];
    $date = $_POST['date'];
    
    // 记录用户
    if (!in_array($name, $users)) {
        $users[] = $name;
        file_put_contents(USERS_FILE, json_encode($users));
    }
    
    // 处理各项目数据
    $projects = ['reading', 'special', 'fitness'];
    $newData = [];
    
    foreach ($projects as $project) {
        if (isset($_POST[$project]) && !empty($_POST[$project])) {
            $score = 0;
            $specialScore = 0;
            
            if ($project === 'reading') {
                $score = $_POST[$project];
            } elseif ($project === 'special') {
                $score = 3; // 优秀分享固定加3分
                $specialScore = $_POST[$project . '_score'];
            } elseif ($project === 'fitness') {
                $score = 1; // 运动打卡每次计1次
            }
            
            $projectName = '';
            switch ($project) {
                case 'reading':
                    $projectName = '读书点评打卡';
                    break;
                case 'special':
                    $projectName = '优秀分享';
                    break;
                case 'fitness':
                    $projectName = '健康运动打卡';
                    break;
            }
            
            $newData[] = [
                'id' => $id . '-' . $project,
                'team' => $team,
                'name' => $name,
                'project' => $projectName,
                'score' => $score,
                'specialScore' => $specialScore,
                'date' => $date
            ];
        }
    }
    
    // 先删除原记录
    if (isset($_POST['id'])) {
        $data = array_filter($data, function($item) use ($id) {
            return strpos($item['id'], $id) !== 0;
        });
    }
    
    // 添加新记录
    $data = array_merge($data, $newData);
    
    file_put_contents(DATA_FILE, json_encode($data));
    header('Location: index.php');
    exit;
}

// 删除数据
if (isset($_GET['delete']) && isAdmin()) {
    $id = $_GET['delete'];
    $data = array_filter($data, function($item) use ($id) {
        return strpos($item['id'], $id) !== 0;
    });
    file_put_contents(DATA_FILE, json_encode(array_values($data)));
    header('Location: index.php');
    exit;
}

// 编辑数据
if (isset($_GET['edit']) && isAdmin()) {
    $id = $_GET['edit'];
    $editData = [];
    
    foreach ($data as $item) {
        if (strpos($item['id'], $id) === 0) {
            $projectKey = '';
            switch ($item['project']) {
                case '读书点评打卡':
                    $projectKey = 'reading';
                    break;
                case '优秀分享':
                    $projectKey = 'special';
                    break;
                case '健康运动打卡':
                    $projectKey = 'fitness';
                    break;
            }
            
            if ($projectKey) {
                $editData[$projectKey] = $item;
            }
        }
    }
}

// 获取日期对应的星期
function getWeekday($dateString) {
    $weekdays = ['日', '一', '二', '三', '四', '五', '六'];
    $date = new DateTime($dateString);
    return '周' . $weekdays[$date->format('w')];
}

// 按周、月、季度统计
function getStats($data, $periodType = 'week') {
    $stats = [];
    
    foreach ($data as $item) {
        $date = new DateTime($item['date']);
        
        if ($periodType === 'week') {
            $period = $date->format('Y-W');
        } elseif ($periodType === 'month') {
            $period = $date->format('Y-m');
        } elseif ($periodType === 'quarter') {
            $quarter = ceil(($date->format('n')) / 3);
            $period = $date->format('Y') . '-Q' . $quarter;
        }
        
        $name = $item['name'];
        $team = $item['team'];
        $project = $item['project'];
        $score = $item['score'];
        $specialScore = $item['specialScore'];
        
        // 初始化统计结构
        if (!isset($stats[$period][$team][$name])) {
            $stats[$period][$team][$name] = [
                'reading' => 0,
                'special' => [],
                'specialTotal' => 0,
                'fitness' => [],
                'fitnessTotal' => 0,
                'total' => 0
            ];
        }
        
        // 按项目分类统计
        if ($project === '读书点评打卡') {
            $stats[$period][$team][$name]['reading'] += $score;
        } elseif ($project === '优秀分享') {
            $stats[$period][$team][$name]['special'][] = $specialScore;
            $stats[$period][$team][$name]['specialTotal'] += 3; // 优秀分享固定加3分
        } elseif ($project === '健康运动打卡') {
            $stats[$period][$team][$name]['fitness'][] = $date->format('Y-m-d');
        }
    }
    
    // 计算总分和运动打卡总分
    foreach ($stats as $period => $teams) {
        foreach ($teams as $team => $members) {
            foreach ($members as $name => $memberData) {
                // 计算运动打卡总分（每周满3次得3分）
                $weekDates = [];
                foreach ($memberData['fitness'] as $dateStr) {
                    $date = new DateTime($dateStr);
                    $week = $date->format('Y-W');
                    if (!isset($weekDates[$week])) {
                        $weekDates[$week] = [];
                    }
                    $weekDates[$week][] = $dateStr;
                }
                
                $fitnessScore = 0;
                foreach ($weekDates as $week => $dates) {
                    if (count($dates) >= 3) {
                        $fitnessScore += 3;
                    }
                }
                
                $stats[$period][$team][$name]['fitnessTotal'] = $fitnessScore;
                
                // 计算读书打卡总分（读书点评+优秀分享基础分）
                $readingTotal = $memberData['reading'] + $memberData['specialTotal'];
                
                // 计算总分
                $total = $readingTotal + $fitnessScore;
                
                $stats[$period][$team][$name]['total'] = $total;
                $stats[$period][$team][$name]['readingTotal'] = $readingTotal;
            }
        }
    }
    
    return $stats;
}

// 获取优秀分享排名
function getSpecialShareRanking($data, $periodType = 'month') {
    $ranking = [];
    
    foreach ($data as $item) {
        if ($item['project'] !== '优秀分享') continue;
        
        $date = new DateTime($item['date']);
        
        if ($periodType === 'week') {
            $period = $date->format('Y-W');
        } elseif ($periodType === 'month') {
            $period = $date->format('Y-m');
        } elseif ($periodType === 'quarter') {
            $quarter = ceil(($date->format('n')) / 3);
            $period = $date->format('Y') . '-Q' . $quarter;
        }
        
        $name = $item['name'];
        $team = $item['team'];
        $specialScore = $item['specialScore'];
        
        if (!isset($ranking[$period][$team][$name])) {
            $ranking[$period][$team][$name] = [
                'scores' => [],
                'total' => 0,
                'count' => 0
            ];
        }
        
        $ranking[$period][$team][$name]['scores'][] = $specialScore;
        $ranking[$period][$team][$name]['total'] += $specialScore;
        $ranking[$period][$team][$name]['count']++;
    }
    
    // 排序
    foreach ($ranking as $period => &$teams) {
        foreach ($teams as $team => &$members) {
            uasort($members, function($a, $b) {
                // 先比较总分
                if ($a['total'] !== $b['total']) {
                    return $b['total'] - $a['total'];
                }
                
                // 再比较100分数量
                $a100 = count(array_filter($a['scores'], function($s) { return $s === 100; }));
                $b100 = count(array_filter($b['scores'], function($s) { return $s === 100; }));
                if ($a100 !== $b100) {
                    return $b100 - $a100;
                }
                
                // 再比较95分数量
                $a95 = count(array_filter($a['scores'], function($s) { return $s === 95; }));
                $b95 = count(array_filter($b['scores'], function($s) { return $s === 95; }));
                if ($a95 !== $b95) {
                    return $b95 - $a95;
                }
                
                // 最后比较90分数量
                $a90 = count(array_filter($a['scores'], function($s) { return $s === 90; }));
                $b90 = count(array_filter($b['scores'], function($s) { return $s === 90; }));
                return $b90 - $a90;
            });
            
            // 添加排名
            $rank = 1;
            $prevScore = null;
            $sameRank = 0;
            foreach ($members as $name => &$member) {
                if ($prevScore !== null && $member['total'] < $prevScore) {
                    $rank += $sameRank + 1;
                    $sameRank = 0;
                } elseif ($prevScore !== null && $member['total'] === $prevScore) {
                    $sameRank++;
                }
                $member['rank'] = $rank;
                $prevScore = $member['total'];
            }
        }
    }
    
    return $ranking;
}

// 获取当前周、月、季度
$currentDate = new DateTime();
$currentWeek = $currentDate->format('Y-W');
$currentMonth = $currentDate->format('Y-m');
$currentQuarter = $currentDate->format('Y') . '-Q' . ceil(($currentDate->format('n')) / 3);

// 统计数据
$weeklyStats = getStats($data, 'week');
$monthlyStats = getStats($data, 'month');
$quarterlyStats = getStats($data, 'quarter');

// 优秀分享排名
$weeklyRanking = getSpecialShareRanking($data, 'week');
$monthlyRanking = getSpecialShareRanking($data, 'month');
$quarterlyRanking = getSpecialShareRanking($data, 'quarter');

// 获取最近20条记录并按日期排序
usort($data, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recentData = array_slice($data, 0, 20);
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>运动读书打卡学分统计表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background-color: #f9f9f9; }
        input, select { margin: 5px 0; padding: 8px; width: 100%; box-sizing: border-box; }
        button { padding: 10px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
        .login-form { max-width: 300px; margin: 0 auto; }
        .error { color: red; }
        .edit-link, .delete-link { margin-right: 10px; color: #0066cc; text-decoration: none; }
        .logout { float: right; }
        .stats-container { display: flex; flex-wrap: wrap; }
        .stats-box { flex: 1; min-width: 300px; margin: 10px; padding: 15px; border: 1px solid #ddd; }
        .ranking { margin-top: 20px; }
        .rules { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; }
        .date-weekday { margin-left: 10px; font-size: 0.9em; color: #666; }
        .project-group { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .project-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    </style>
</head>
<body>
    <h1>运动读书打卡学分统计表</h1>
    
    <?php if (!isAdmin()): ?>
        <div class="login-form">
            <h2>管理员登录</h2>
            <?php if (isset($loginError)): ?>
                <p class="error"><?php echo $loginError; ?></p>
            <?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="用户名" required><br>
                <input type="password" name="password" placeholder="密码" required><br>
                <button type="submit" name="login">登录</button>
            </form>
        </div>
    <?php else: ?>
        <a href="?logout=1" class="logout">退出登录</a>
        
        <!-- 数据录入表单 -->
        <h2><?php echo isset($editData) ? '编辑' : '添加'; ?>打卡记录</h2>
        <form method="post">
            <?php if (isset($editData) && !empty($editData)): ?>
                <input type="hidden" name="id" value="<?php echo key(array_slice($editData, 0, 1)) ? explode('-', current($editData)['id'])[0] : uniqid(); ?>">
            <?php endif; ?>
            
            <select name="team" required>
                <option value="">选择团队</option>
                <option value="乐观组" <?php echo (isset($editData) && current($editData)['team'] === '乐观组') ? 'selected' : ''; ?>>乐观组</option>
                <option value="利他组" <?php echo (isset($editData) && current($editData)['team'] === '利他组') ? 'selected' : ''; ?>>利他组</option>
            </select>
            
            <select name="name" required>
                <option value="">选择/输入姓名</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user); ?>" <?php echo (isset($editData) && current($editData)['name'] === $user) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user); ?></option>
                <?php endforeach; ?>
                <option value="_new_">-- 新增姓名 --</option>
            </select>
            <input type="text" name="new_name" placeholder="输入新姓名" <?php echo (isset($editData) && !empty($editData)) ? 'disabled' : ''; ?>>
            
            <div class="project-group">
                <h3>读书点评打卡</h3>
                <select name="reading" required>
                    <option value="">选择得分</option>
                    <option value="1" <?php echo (isset($editData['reading']) && $editData['reading']['score'] === 1) ? 'selected' : ''; ?>>1分（参与）</option>
                    <option value="0" <?php echo (isset($editData['reading']) && $editData['reading']['score'] === 0) ? 'selected' : ''; ?>>0分（未参与）</option>
                </select>
            </div>
            
            <div class="project-group">
                <h3>优秀分享</h3>
                <select name="special" required>
                    <option value="">是否优秀分享</option>
                    <option value="3" <?php echo (isset($editData['special'])) ? 'selected' : ''; ?>>是（额外加3分）</option>
                    <option value="0">否</option>
                </select>
                <select name="special_score" <?php echo (isset($editData['special'])) ? '' : 'disabled'; ?>>
                    <option value="">选择优秀分享得分</option>
                    <option value="90" <?php echo (isset($editData['special']) && $editData['special']['specialScore'] === 90) ? 'selected' : ''; ?>>90分</option>
                    <option value="95" <?php echo (isset($editData['special']) && $editData['special']['specialScore'] === 95) ? 'selected' : ''; ?>>95分</option>
                    <option value="100" <?php echo (isset($editData['special']) && $editData['special']['specialScore'] === 100) ? 'selected' : ''; ?>>100分</option>
                </select>
            </div>
            
            <div class="project-group">
                <h3>健康运动打卡</h3>
                <select name="fitness" required>
                    <option value="">是否打卡</option>
                    <option value="1" <?php echo (isset($editData['fitness'])) ? 'selected' : ''; ?>>是（计1次）</option>
                    <option value="0">否</option>
                </select>
            </div>
            
            <div>
                <label>日期：</label>
                <input type="date" name="date" value="<?php echo isset($editData) ? current($editData)['date'] : date('Y-m-d'); ?>" required>
                <span class="date-weekday" id="weekday-display">
                    <?php echo isset($editData) ? getWeekday(current($editData)['date']) : getWeekday(date('Y-m-d')); ?>
                </span>
            </div>
            
            <button type="submit" name="submit"><?php echo isset($editData) ? '保存修改' : '添加记录'; ?></button>
        </form>
    <?php endif; ?>
    
    <!-- 最近20条记录 -->
    <h2>最近记录（20条）</h2>
    <table>
        <tr>
            <th>团队</th>
            <th>姓名</th>
            <th>项目</th>
            <th>得分</th>
            <th>优秀分享得分</th>
            <th>日期</th>
            <th>星期</th>
            <?php if (isAdmin()): ?>
                <th>操作</th>
            <?php endif; ?>
        </tr>
        <?php foreach ($recentData as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['team']); ?></td>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['project']); ?></td>
                <td><?php echo htmlspecialchars($item['score']); ?></td>
                <td><?php echo $item['specialScore'] > 0 ? htmlspecialchars($item['specialScore']) : '-'; ?></td>
                <td><?php echo htmlspecialchars($item['date']); ?></td>
                <td><?php echo getWeekday($item['date']); ?></td>
                <?php if (isAdmin()): ?>
                    <td>
                        <a href="?edit=<?php echo explode('-', $item['id'])[0]; ?>" class="edit-link">编辑</a>
                        <a href="?delete=<?php echo explode('-', $item['id'])[0]; ?>" class="delete-link" onclick="return confirm('确定要删除这条记录吗？')">删除</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <!-- 统计信息 -->
    <?php if (!empty($monthlyStats[$currentMonth])): ?>
        <h2>本月数据统计（<?php echo date('Y年m月', strtotime($currentMonth . '-01')); ?>）</h2>
        <div class="stats-container">
            <?php foreach (['乐观组', '利他组'] as $team): ?>
                <?php if (isset($monthlyStats[$currentMonth][$team])): ?>
                    <div class="stats-box">
                        <h3><?php echo $team; ?></h3>
                        <table>
                            <tr>
                                <th>姓名</th>
                                <th>读书点评打卡</th>
                                <th>优秀分享基础分</th>
                                <th>读书打卡学分</th>
                                <th>健康运动打卡</th>
                                <th>总分</th>
                            </tr>
                            <?php foreach ($monthlyStats[$currentMonth][$team] as $name => $stats): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo $stats['reading']; ?></td>
                                    <td><?php echo $stats['specialTotal']; ?></td>
                                    <td><?php echo $stats['readingTotal']; ?></td>
                                    <td><?php echo $stats['fitnessTotal']; ?></td>
                                    <td><?php echo $stats['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- 优秀分享排名 -->
    <div class="ranking">
        <h2>优秀分享学分排名（<?php echo date('Y年m月', strtotime($currentMonth . '-01')); ?>）</h2>
        <?php foreach (['乐观组', '利他组'] as $team): ?>
            <?php if (isset($monthlyRanking[$currentMonth][$team])): ?>
                <h3><?php echo $team; ?></h3>
                <table>
                    <tr>
                        <th>排名</th>
                        <th>姓名</th>
                        <th>次数</th>
                        <th>总分</th>
                        <th>得分详情</th>
                    </tr>
                    <?php foreach ($monthlyRanking[$currentMonth][$team] as $name => $ranking): ?>
                        <tr>
                            <td><?php echo $ranking['rank']; ?></td>
                            <td><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo $ranking['count']; ?></td>
                            <td><?php echo $ranking['total']; ?></td>
                            <td><?php echo implode(', ', $ranking['scores']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <!-- 本月数据表格 -->
    <?php if (!empty($data)): ?>
        <h2>本月全部数据</h2>
        <table>
            <tr>
                <th>团队</th>
                <th>姓名</th>
                <th>项目</th>
                <th>得分</th>
                <th>优秀分享得分</th>
                <th>日期</th>
                <th>星期</th>
            </tr>
            <?php foreach ($data as $item): 
                $itemMonth = date('Y-m', strtotime($item['date']));
                if ($itemMonth !== $currentMonth) continue;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['team']); ?></td>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['project']); ?></td>
                    <td><?php echo htmlspecialchars($item['score']); ?></td>
                    <td><?php echo $item['specialScore'] > 0 ? htmlspecialchars($item['specialScore']) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($item['date']); ?></td>
                    <td><?php echo getWeekday($item['date']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    
    <!-- 规则说明 -->
    <div class="rules">
        <h2>打分规则</h2>
        <p>1）学习日晚上20点前读书+分享并接龙积1分，学习日晚上22点前读书+点评回应并接龙积1分；</p>
        <p>2）优秀分享额外积3分（优秀分享班主任按照标椎评选）；</p>
        <p>3）按要求完成一周至少运动3次的，一周积1次，一次积3分，完成两次及两次以下不积分。</p>
    </div>

    <script>
        // 表单交互逻辑
        document.addEventListener('DOMContentLoaded', function() {
            const nameSelect = document.querySelector('select[name="name"]');
            const newNameInput = document.querySelector('input[name="new_name"]');
            const dateInput = document.querySelector('input[name="date"]');
            const weekdayDisplay = document.getElementById('weekday-display');
            const specialSelect = document.querySelector('select[name="special"]');
            const specialScoreSelect = document.querySelector('select[name="special_score"]');
            
            if (nameSelect && newNameInput) {
                nameSelect.addEventListener('change', function() {
                    if (this.value === '_new_') {
                        newNameInput.disabled = false;
                        newNameInput.required = true;
                    } else {
                        newNameInput.disabled = true;
                        newNameInput.required = false;
                        newNameInput.value = '';
                    }
                });
            }
            
            if (dateInput && weekdayDisplay) {
                dateInput.addEventListener('change', function() {
                    const date = new Date(this.value);
                    const weekdays = ['日', '一', '二', '三', '四', '五', '六'];
                    const weekday = '周' + weekdays[date.getDay()];
                    weekdayDisplay.textContent = weekday;
                });
            }
            
            if (specialSelect && specialScoreSelect) {
                specialSelect.addEventListener('change', function() {
                    if (this.value === '3') {
                        specialScoreSelect.disabled = false;
                        specialScoreSelect.required = true;
                    } else {
                        specialScoreSelect.disabled = true;
                        specialScoreSelect.required = false;
                        specialScoreSelect.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>    
