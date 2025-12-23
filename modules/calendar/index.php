<?php
require_once '../../includes/header.php';

$auth->requireLogin();
$conn = getDBConnection();

// Get events for the current month
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

$sql = "SELECT * FROM calendar_events WHERE MONTH(start_date) = ? AND YEAR(start_date) = ? ORDER BY start_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">School Calendar</h1>
        <?php if (in_array($_SESSION['role'], ['admin', 'registrar'])): ?>
        <a href="add.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add Event
        </a>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                    </h6>
                    <div>
                        <?php 
                            $prev_month = $month - 1;
                            $prev_year = $year;
                            if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                            
                            $next_month = $month + 1;
                            $next_year = $year;
                            if ($next_month > 12) { $next_month = 1; $next_year++; }
                        ?>
                        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Simple Calendar View -->
                    <div class="table-responsive">
                        <table class="table table-bordered" id="calendarTable">
                            <thead>
                                <tr>
                                    <th>Sun</th>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $first_day = mktime(0, 0, 0, $month, 1, $year);
                                $days_in_month = date('t', $first_day);
                                $day_of_week = date('w', $first_day);
                                $day = 1;
                                
                                echo "<tr>";
                                for ($i = 0; $i < $day_of_week; $i++) {
                                    echo "<td></td>";
                                }
                                
                                while ($day <= $days_in_month) {
                                    if ($day_of_week == 7) {
                                        $day_of_week = 0;
                                        echo "</tr><tr>";
                                    }
                                    
                                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $is_today = ($current_date == date('Y-m-d'));
                                    $class = $is_today ? 'bg-light font-weight-bold' : '';
                                    
                                    echo "<td class='$class' style='height: 100px; vertical-align: top;'>";
                                    echo "<div class='d-flex justify-content-between'>";
                                    echo "<span>$day</span>";
                                    if ($is_today) echo "<span class='badge bg-primary'>Today</span>";
                                    echo "</div>";
                                    
                                    // Find events for this day
                                    $result->data_seek(0); // Reset pointer
                                    while ($event = $result->fetch_assoc()) {
                                        if (date('Y-m-d', strtotime($event['start_date'])) == $current_date) {
                                            $color = 'primary';
                                            if ($event['type'] == 'exam') $color = 'danger';
                                            if ($event['type'] == 'holiday') $color = 'success';
                                            
                                            echo "<div class='mt-1'>";
                                            echo "<a href='view.php?id={$event['id']}' class='badge bg-$color text-white text-decoration-none d-block text-truncate' title='{$event['title']}'>";
                                            echo htmlspecialchars($event['title']);
                                            echo "</a>";
                                            echo "</div>";
                                        }
                                    }
                                    
                                    echo "</td>";
                                    
                                    $day++;
                                    $day_of_week++;
                                }
                                
                                while ($day_of_week < 7) {
                                    echo "<td></td>";
                                    $day_of_week++;
                                }
                                echo "</tr>";
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upcoming Events</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get upcoming 5 events
                    $upcoming_sql = "SELECT * FROM calendar_events WHERE start_date >= CURDATE() ORDER BY start_date ASC LIMIT 5";
                    $upcoming_result = $conn->query($upcoming_sql);
                    
                    if ($upcoming_result && $upcoming_result->num_rows > 0):
                        while ($event = $upcoming_result->fetch_assoc()):
                    ?>
                    <div class="mb-3 pb-3 border-bottom">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 font-weight-bold"><?php echo htmlspecialchars($event['title']); ?></h6>
                            <small class="text-muted"><?php echo date('M d', strtotime($event['start_date'])); ?></small>
                        </div>
                        <p class="mb-1 small"><?php echo htmlspecialchars($event['description']); ?></p>
                        <small class="text-<?php echo $event['type'] == 'exam' ? 'danger' : ($event['type'] == 'holiday' ? 'success' : 'primary'); ?>">
                            <i class="fas fa-tag me-1"></i> <?php echo ucfirst($event['type']); ?>
                        </small>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <p class="text-muted text-center">No upcoming events.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>