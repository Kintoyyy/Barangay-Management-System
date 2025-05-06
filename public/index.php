<?php
require __DIR__ . '/../config/bootstrap.php';



// if ( !is_logged_in() ) {
//     header( 'Location: ../auth/login.php' );
//     exit;
// }

$db = Database::getInstance()->getConnection();

$summaryData = [];
$chartData   = [];
$message     = '';
$messageType = '';

try {
    $stmt                            = $db->query( "SELECT COUNT(*) FROM residents" );
    $summaryData[ 'totalResidents' ] = $stmt->fetchColumn();

    $stmt                             = $db->query( "SELECT COUNT(*) FROM residents WHERE is_active = TRUE" );
    $summaryData[ 'activeResidents' ] = $stmt->fetchColumn();

    $stmt                             = $db->query( "SELECT COUNT(*) FROM households" );
    $summaryData[ 'totalHouseholds' ] = $stmt->fetchColumn();

    $stmt                             = $db->query( "SELECT COUNT(*) FROM barangay_officials WHERE is_active = TRUE" );
    $summaryData[ 'activeOfficials' ] = $stmt->fetchColumn();

    $stmt                               = $db->query( "SELECT COUNT(*) FROM financial_transactions" );
    $summaryData[ 'totalTransactions' ] = $stmt->fetchColumn();

    $stmt                          = $db->query( "SELECT SUM(amount) FROM financial_transactions WHERE transaction_type = 'Fee Payment'" );
    $summaryData[ 'totalRevenue' ] = $stmt->fetchColumn() ?? 0;

    $stmt                           = $db->query( "SELECT SUM(amount) FROM financial_transactions WHERE transaction_type = 'Expense'" );
    $summaryData[ 'totalExpenses' ] = $stmt->fetchColumn() ?? 0;

    $stmt                            = $db->query( "SELECT COUNT(*) FROM documents" );
    $summaryData[ 'totalDocuments' ] = $stmt->fetchColumn();

    $stmt                            = $db->query( "SELECT COUNT(*) FROM complaints WHERE status = 'Open'" );
    $summaryData[ 'openComplaints' ] = $stmt->fetchColumn();

    $stmt                            = $db->query( "SELECT p.purok_number, COUNT(r.resident_id) as resident_count
                        FROM purok p
                        LEFT JOIN residents r ON p.purok_id = r.purok_id
                        GROUP BY p.purok_number
                        ORDER BY p.purok_number" );
    $chartData[ 'residentsByPurok' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                             = $db->query( "SELECT gender, COUNT(*) as count
                        FROM residents
                        WHERE gender IS NOT NULL AND gender != ''
                        GROUP BY gender" );
    $chartData[ 'residentsByGender' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                                  = $db->query( "SELECT is_voter, COUNT(*) as count
                        FROM residents
                        GROUP BY is_voter" );
    $chartData[ 'residentsByVoterStatus' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                              = $db->query( "SELECT transaction_type, SUM(amount) as total_amount
                        FROM financial_transactions
                        GROUP BY transaction_type" );
    $chartData[ 'transactionsByType' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                             = $db->query( "SELECT status, COUNT(*) as count
                        FROM documents
                        GROUP BY status" );
    $chartData[ 'documentsByStatus' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                              = $db->query( "SELECT status, COUNT(*) as count
                        FROM complaints
                        GROUP BY status" );
    $chartData[ 'complaintsByStatus' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                               = $db->query( "SELECT position, COUNT(*) as count
                        FROM barangay_officials
                        WHERE is_active = TRUE
                        GROUP BY position
                        ORDER BY position" );
    $chartData[ 'officialsByPosition' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                               = $db->query( "SELECT record_type, COUNT(*) as count
                        FROM health_records
                        GROUP BY record_type" );
    $chartData[ 'healthRecordsByType' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );

    $stmt                                     = $db->query( "SELECT strftime('%Y-%m', date_registered) as month, COUNT(*) as count
                        FROM residents
                        GROUP BY month
                        ORDER BY month" );
    $chartData[ 'residentRegistrationTrend' ] = $stmt->fetchAll( PDO::FETCH_ASSOC );


}
catch ( PDOException $e ) {
    $message     = 'Error loading dashboard data: ' . $e->getMessage();
    $messageType = 'danger';
    $summaryData = [];
    $chartData   = [];
}

$chartDataJson = json_encode( $chartData );

?>

<!doctype html>
<html lang="en"
    data-bs-core="modern">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1">
    <title>Barangay Dashboard</title>
    <?php include_once INCLUDES_PATH . '/styles.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script
        src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        .summary-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .summary-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }

        .summary-card p {
            color: #666;
        }

        .chart-container {
            position: relative;
            height: 300px;
            /* Adjust as needed */
            margin-bottom: 30px;
        }
    </style>
</head>

<body>
    <?php include_once 'navbar.php'; ?>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Barangay Dashboard</h1>
                <p class="text-muted">Overview of key barangay statistics</p>
            </div>
        </div>

        <?php if ( !empty( $message ) ): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show"
                role="alert">
                <?= htmlspecialchars( $message ) ?>
                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-primary text-white">
                    <i class="bi bi-people-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'totalResidents' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Total Residents</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-success text-white">
                    <i class="bi bi-person-check-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'activeResidents' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Active Residents</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-info text-white">
                    <i class="bi bi-house-door-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'totalHouseholds' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Total Households</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-warning text-dark">
                    <i class="bi bi-person-badge-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'activeOfficials' ] ?? 0 ) ?></h3>
                    <p>Active Officials</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-secondary text-white">
                    <i class="bi bi-receipt-cutoff"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'totalTransactions' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Total Transactions</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-light text-dark">
                    <i class="bi bi-cash-stack"
                        style="font-size: 2rem;"></i>
                    <h3>₱<?= number_format( $summaryData[ 'totalRevenue' ] ?? 0, 2 ) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-danger text-white">
                    <i class="bi bi-graph-down"
                        style="font-size: 2rem;"></i>
                    <h3>₱<?= number_format( $summaryData[ 'totalExpenses' ] ?? 0, 2 ) ?></h3>
                    <p class=" text-white">Total Expenses</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-primary text-white">
                    <i class="bi bi-file-earmark-text-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'totalDocuments' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Documents Requested</p>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="summary-card bg-danger text-white">
                    <i class="bi bi-megaphone-fill"
                        style="font-size: 2rem;"></i>
                    <h3><?= htmlspecialchars( $summaryData[ 'openComplaints' ] ?? 0 ) ?></h3>
                    <p class=" text-white">Open Complaints</p>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Residents by Purok</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="residentsByPurokChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Residents by Gender</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="residentsByGenderChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Residents by Voter Status</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="residentsByVoterStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Financial Transactions by Type</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="transactionsByTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Documents by Status</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="documentsByStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Complaints by Status</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="complaintsByStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Active Officials by Position</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="officialsByPositionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Health Records by Type</div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="healthRecordsByTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">Resident Registration Trend (Monthly)</div>
                    <div class="card-body">
                        <div class="chart-container"
                            style="height: 400px;">
                            <canvas id="residentRegistrationTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include_once INCLUDES_PATH . '/scripts.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });

            // Chart Data from PHP
            const chartData = <?= $chartDataJson ?>;

            // Helper function to generate random colors for charts
            function generateColors(num) {
                const colors = [];
                for (let i = 0; i < num; i++) {
                    const hue = (i * 137.508) % 360; // Golden angle approximation for distinct colors
                    colors.push(`hsl(${hue}, 70%, 60%)`);
                }
                return colors;
            }

            // Residents by Purok Chart
            if (chartData.residentsByPurok && chartData.residentsByPurok.length > 0) {
                const ctx = document.getElementById('residentsByPurokChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.residentsByPurok.map(item => item.purok_number),
                        datasets: [{
                            label: 'Number of Residents',
                            data: chartData.residentsByPurok.map(item => item.resident_count),
                            backgroundColor: generateColors(chartData.residentsByPurok.length),
                            borderColor: generateColors(chartData.residentsByPurok.length).map(color => color.replace('60%', '40%')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                document.getElementById('residentsByPurokChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Residents by Purok.</p>';
            }


            // Residents by Gender Chart
            if (chartData.residentsByGender && chartData.residentsByGender.length > 0) {
                const ctx = document.getElementById('residentsByGenderChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.residentsByGender.map(item => item.gender || 'Not Specified'),
                        datasets: [{
                            data: chartData.residentsByGender.map(item => item.count),
                            backgroundColor: generateColors(chartData.residentsByGender.length),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: false,
                                text: 'Residents by Gender'
                            }
                        }
                    }
                });
            } else {
                document.getElementById('residentsByGenderChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Residents by Gender.</p>';
            }


            // Residents by Voter Status Chart
            if (chartData.residentsByVoterStatus && chartData.residentsByVoterStatus.length > 0) {
                const ctx = document.getElementById('residentsByVoterStatusChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.residentsByVoterStatus.map(item => item.is_voter == 1 ? 'Voter' : 'Non-Voter'),
                        datasets: [{
                            data: chartData.residentsByVoterStatus.map(item => item.count),
                            backgroundColor: ['#36a2eb', '#ff6384'], // Specific colors for boolean
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: false,
                                text: 'Residents by Voter Status'
                            }
                        }
                    }
                });
            } else {
                document.getElementById('residentsByVoterStatusChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Residents by Voter Status.</p>';
            }

            // Financial Transactions by Type Chart
            if (chartData.transactionsByType && chartData.transactionsByType.length > 0) {
                const ctx = document.getElementById('transactionsByTypeChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.transactionsByType.map(item => item.transaction_type),
                        datasets: [{
                            label: 'Total Amount (₱)',
                            data: chartData.transactionsByType.map(item => item.total_amount),
                            backgroundColor: generateColors(chartData.transactionsByType.length),
                            borderColor: generateColors(chartData.transactionsByType.length).map(color => color.replace('60%', '40%')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    // Add currency formatting to ticks
                                    callback: function (value, index, values) {
                                        return '₱' + value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                document.getElementById('transactionsByTypeChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Transactions by Type.</p>';
            }

            // Documents by Status Chart
            if (chartData.documentsByStatus && chartData.documentsByStatus.length > 0) {
                const ctx = document.getElementById('documentsByStatusChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.documentsByStatus.map(item => item.status),
                        datasets: [{
                            data: chartData.documentsByStatus.map(item => item.count),
                            backgroundColor: generateColors(chartData.documentsByStatus.length),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: false,
                                text: 'Documents by Status'
                            }
                        }
                    }
                });
            } else {
                document.getElementById('documentsByStatusChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Documents by Status.</p>';
            }

            // Complaints by Status Chart
            if (chartData.complaintsByStatus && chartData.complaintsByStatus.length > 0) {
                const ctx = document.getElementById('complaintsByStatusChart').getContext('2d');
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.complaintsByStatus.map(item => item.status),
                        datasets: [{
                            data: chartData.complaintsByStatus.map(item => item.count),
                            backgroundColor: generateColors(chartData.complaintsByStatus.length),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            },
                            title: {
                                display: false,
                                text: 'Complaints by Status'
                            }
                        }
                    }
                });
            } else {
                document.getElementById('complaintsByStatusChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Complaints by Status.</p>';
            }

            // Officials by Position Chart
            if (chartData.officialsByPosition && chartData.officialsByPosition.length > 0) {
                const ctx = document.getElementById('officialsByPositionChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.officialsByPosition.map(item => item.position),
                        datasets: [{
                            label: 'Number of Officials',
                            data: chartData.officialsByPosition.map(item => item.count),
                            backgroundColor: generateColors(chartData.officialsByPosition.length),
                            borderColor: generateColors(chartData.officialsByPosition.length).map(color => color.replace('60%', '40%')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                document.getElementById('officialsByPositionChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Officials by Position.</p>';
            }

            // Health Records by Type Chart
            if (chartData.healthRecordsByType && chartData.healthRecordsByType.length > 0) {
                const ctx = document.getElementById('healthRecordsByTypeChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.healthRecordsByType.map(item => item.record_type),
                        datasets: [{
                            label: 'Number of Records',
                            data: chartData.healthRecordsByType.map(item => item.count),
                            backgroundColor: generateColors(chartData.healthRecordsByType.length),
                            borderColor: generateColors(chartData.healthRecordsByType.length).map(color => color.replace('60%', '40%')),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                document.getElementById('healthRecordsByTypeChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Health Records by Type.</p>';
            }

            // Resident Registration Trend Chart (Line Chart)
            if (chartData.residentRegistrationTrend && chartData.residentRegistrationTrend.length > 0) {
                const ctx = document.getElementById('residentRegistrationTrendChart').getContext('2d');

                // Prepare data for line chart
                const labels = chartData.residentRegistrationTrend.map(item => item.month + '-01'); // Use first day of month for date parsing
                const data = chartData.residentRegistrationTrend.map(item => item.count);

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels, // Dates
                        datasets: [{
                            label: 'New Residents Registered',
                            data: data,
                            fill: true, // Fill area under the line
                            backgroundColor: 'rgba(54, 162, 235, 0.2)', // Light blue fill
                            borderColor: 'rgba(54, 162, 235, 1)', // Blue line
                            tension: 0.1 // Smooth the line
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time', // Use 'time' scale for dates
                                time: {
                                    unit: 'month', // Display by month
                                    tooltipFormat: 'MMM yyyy', // Format tooltip
                                    displayFormats: {
                                        month: 'MMM yyyy' // Format axis labels
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Month'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Residents'
                                },
                                ticks: {
                                    stepSize: 1 // Ensure whole numbers for counts
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            } else {
                document.getElementById('residentRegistrationTrendChart').closest('.card-body').innerHTML = '<p class="text-center text-muted">No data available for Resident Registration Trend.</p>';
            }

        });
    </script>
</body>

</html>