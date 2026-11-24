<?php
use CRM_Enhancedjobmanager_ExtensionUtil as E;

class CRM_Enhancedjobmanager_Page_JobStats extends CRM_Core_Page {
  public $_domain_id;

  public function __construct() {
    $this->_domain_id = CRM_Core_Config::domainID();
    parent::__construct();
  }

  public function run() {
    // Set page title
    CRM_Utils_System::setTitle(ts('Job Log Statistics'));

    // Add CSS and JS resources
    CRM_Core_Resources::singleton()
      ->addStyleFile('com.skvare.enhancedjobmanager', 'css/jobstats.css')
      ->addScriptFile('com.skvare.enhancedjobmanager', 'js/jobstats.js')
      ->addScriptUrl('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js');


    // Get initial data for the page
    $jobStats = $this->getJobStatistics();
    $jobList = $this->getJobList();
    $recentExecutions = $this->getRecentExecutions();

    // Assign variables to template
    $this->assign('jobStats', $jobStats);
    $this->assign('jobList', $jobList);
    $this->assign('recentExecutions', $recentExecutions);
    $this->assign('dateRanges', $this->getDateRangeOptions());

    parent::run();
  }

  /**
   * Get job statistics summary
   * @param array $filters
   * @return array
   */
  public function getJobStatistics($filters = []) {
    $whereClause = $this->buildWhereClause($filters);
    // Get total executions
    $totalExecutions = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_job_log WHERE 1=1 and description like 'Starting execution%' $whereClause"
    );

    // Get success/error counts
    $successCount = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_job_log
       WHERE (data NOT LIKE '%error%' AND data NOT LIKE '%failed%')
       AND 1=1 and description like 'Finished execution%' $whereClause"
    );

    // Get error count
    $errorCount = $totalExecutions - $successCount;

    // Get average duration (if run_time field exists and contains duration info)
    $whereClause = $this->buildWhereClause($filters, 'start_log');
    $avgDuration = CRM_Core_DAO::singleValueQuery("
    SELECT
      AVG(TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time)) as avg_duration
    FROM civicrm_job_log start_log
    INNER JOIN civicrm_job_log finish_log ON (
      start_log.job_id = finish_log.job_id
      AND finish_log.run_time > start_log.run_time
      AND finish_log.run_time = (
        SELECT MIN(f2.run_time)
        FROM civicrm_job_log f2
        WHERE f2.job_id = start_log.job_id
          AND f2.run_time > start_log.run_time
          AND (f2.description LIKE '%Finished execution%')
      )
    )
    WHERE (start_log.description LIKE 'Starting execution%')
      AND (finish_log.description LIKE '%Finished execution%')
      AND TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time) BETWEEN 1 AND 86400
      $whereClause
  ");
    return [
      'total_executions' => $totalExecutions,
      'success_count' => $successCount,
      'error_count' => $errorCount,
      'success_rate' => $totalExecutions > 0 ? round(($successCount / $totalExecutions) * 100, 1) : 0,
      'error_rate' => $totalExecutions > 0 ? round(($errorCount / $totalExecutions) * 100, 1) : 0,
      'avg_duration' => round($avgDuration, 2),
    ];
  }

  /**
   * Get list of available jobs
   * @return array
   */
  public function getJobList() {
    $query = "
      SELECT DISTINCT
        j.*,
        COUNT(jl.id) as execution_count,
        SUM(CASE WHEN jl.data NOT LIKE '%error%' AND jl.data NOT LIKE '%failed%' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN jl.data LIKE '%error%' OR jl.data LIKE '%failed%' THEN 1 ELSE 0 END) as error_count
      FROM civicrm_job j
      INNER JOIN civicrm_job_log jl ON j.id = jl.job_id
        AND jl.run_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      WHERE j.is_active = 1
        AND j.domain_id = {$this->_domain_id}
      GROUP BY j.id, j.name, j.api_entity, j.api_action, j.description, j.is_active, j.last_run
      ORDER BY j.name
    ";

    $dao = CRM_Core_DAO::executeQuery($query);
    $jobs = [];
    $page = new CRM_Enhancedjobmanager_Page_Job();
    while ($dao->fetch()) {
      $errorRate = $dao->execution_count > 0 ? round(($dao->error_count / $dao->execution_count) * 100, 1) : 0;
      $job = $dao->toArray();
      $job['api_call'] = $dao->api_entity . '.' . $dao->api_action;
      $job['next_run'] = $page->predictNextRun($dao);
      $job['status'] = $this->determineJobStatus($dao->error_count, $dao->execution_count, $dao->is_active);
      $job['error_rate'] = $errorRate;
      $jobs[] = $job;
      /*
      $jobs[] = [
        'id' => $dao->id,
        'name' => $dao->name,
        'api_call' => $dao->api_entity . '.' . $dao->api_action,
        'description' => $dao->description,
        'is_active' => $dao->is_active,
        'last_run' => $dao->last_run,
        'next_run' => date('Y-m-d H:i:s', strtotime($dao->last_run) + 86400), // Assuming daily jobs
        'execution_count' => $dao->execution_count,
        'success_count' => $dao->success_count,
        'error_count' => $dao->error_count,
        'error_rate' => $errorRate,
        'status' => $this->determineJobStatus($dao->error_count, $dao->execution_count, $dao->is_active)
      ];
      */
    }

    return $jobs;
  }



  /**
   * Get recent job executions
   * @param int $limit
   * @return array
   */
  public function getRecentExecutions($limit = 50) {
    // Add filters if any
    $query = "
    SELECT
  j.id,
  j.name,
  j.description,
  j.api_entity,
  j.api_action,
  j.last_run,
  j.last_run_end,
  j.is_active,
  TIMESTAMPDIFF(SECOND, j.last_run, j.last_run_end) as duration_seconds,
  CASE
    -- Never executed
    WHEN j.last_run IS NULL THEN 'NEVER_RUN'

    -- Currently running (started within last hour, no end time)
    WHEN j.last_run_end IS NULL AND j.last_run > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'RUNNING'

    -- Long running job (started 1-4 hours ago, no end time)
    WHEN j.last_run_end IS NULL AND j.last_run BETWEEN DATE_SUB(NOW(), INTERVAL 4 HOUR) AND DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'LONG_RUNNING'

    -- Stuck/Failed (started more than 4 hours ago, no end time)
    WHEN j.last_run_end IS NULL AND j.last_run < DATE_SUB(NOW(), INTERVAL 4 HOUR) THEN 'STUCK_OR_FAILED'

    -- Completed successfully
    WHEN j.last_run_end IS NOT NULL AND j.last_run_end >= j.last_run THEN 'COMPLETED'

    -- Data inconsistency (end time before start time)
    WHEN j.last_run_end IS NOT NULL AND j.last_run_end < j.last_run THEN 'DATA_ERROR'

    ELSE 'UNKNOWN'
  END as status_code,

  -- Health indicator
  CASE
    WHEN j.is_active = 0 THEN 'INACTIVE'
    WHEN j.last_run IS NULL THEN 'NO_HISTORY'
    WHEN j.last_run_end IS NULL AND j.last_run > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'HEALTHY'
    WHEN j.last_run_end IS NULL AND j.last_run < DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'UNHEALTHY'
    WHEN j.last_run_end IS NOT NULL AND j.last_run_end >= j.last_run AND j.last_run > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'HEALTHY'
    WHEN j.last_run_end IS NOT NULL AND j.last_run_end >= j.last_run AND j.last_run <= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'STALE'
    ELSE 'NEEDS_ATTENTION'
  END as health_status

FROM civicrm_job j
where j.domain_id = {$this->_domain_id} and j.is_active = 1
ORDER BY j.last_run DESC
    LIMIT $limit
    ";
    $dao = CRM_Core_DAO::executeQuery($query);
    $executions = [];

    while ($dao->fetch()) {
      $executions[] = [
        'id' => $dao->id,
        'job_name' => $dao->name,
        'api_call' => $dao->api_entity . '.' . $dao->api_action,
        'description' => $dao->description,
        'run_time' => $dao->last_run_end,
        'duration' => $dao->duration_seconds,
        'status' => $dao->status_code,
        'health' => $dao->health_status,
      ];
    }

    return $executions;
  }

  /**
   * Get chart data for specified period and job
   * @param string $chartType
   * @param array $filters
   * @return array
   */
  public function getChartData($chartType, $filters = []) {
    $whereClause = $this->buildWhereClause($filters);
    $days = $filters['days'] ?? 30;
    $interval = $this->getIntervalByDays($days);

    switch ($chartType) {
      case 'executions':
        return $this->getExecutionsChartData($whereClause, $interval, $days);
      case 'duration':
        $whereClause = $this->buildWhereClause($filters, 'start_log');
        return $this->getDurationChartData($whereClause, $interval, $days);
      case 'errors':
        return $this->getErrorsChartData($whereClause, $interval, $days);
      case 'success':
        return $this->getSuccessRateChartData($whereClause, $interval, $days);
      default:
        return [];
    }
  }

  /**
   * Get executions chart data
   */
  private function getExecutionsChartData($whereClause, $interval, $days) {
    $query = "
      SELECT
        DATE_FORMAT(run_time, $interval) as period,
        COUNT(*) as executions
      FROM civicrm_job_log
      WHERE description like 'Starting execution%' AND run_time >= DATE_SUB(NOW(), INTERVAL $days DAY) $whereClause
      GROUP BY DATE_FORMAT(run_time, $interval)
      ORDER BY period
    ";
    return $this->executeChartQuery($query);
  }

  /**
   * Get duration chart data
   */
  private function getDurationChartData($whereClause, $interval, $days) {
    $query = "
    SELECT
      DATE_FORMAT(start_log.run_time, $interval) as period,
     AVG(TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time)) as avg_duration
     -- ROUND(AVG(TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time) / 60), 2) as avg_duration
     -- COUNT(*) as job_count,
     -- MIN(TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time)) as min_duration_seconds,
     -- MAX(TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time)) as max_duration_seconds
    FROM civicrm_job_log start_log
    INNER JOIN civicrm_job_log finish_log ON (
      start_log.job_id = finish_log.job_id
      AND finish_log.run_time > start_log.run_time
      AND finish_log.run_time = (
        SELECT MIN(f2.run_time)
        FROM civicrm_job_log f2
        WHERE f2.job_id = start_log.job_id
          AND f2.run_time > start_log.run_time
          AND (f2.description LIKE '%Finished execution%')
      )
    )
    WHERE (start_log.description LIKE 'Starting execution%')
      AND (finish_log.description LIKE '%Finished execution%')
      AND start_log.run_time >= DATE_SUB(NOW(), INTERVAL $days DAY)
      AND TIMESTAMPDIFF(SECOND, start_log.run_time, finish_log.run_time) BETWEEN 1 AND 86400
      $whereClause
    GROUP BY DATE_FORMAT(start_log.run_time, $interval)
    ORDER BY period
  ";

    return $this->executeChartQuery($query);
  }

  /**
   * Get errors chart data
   */
  private function getErrorsChartData($whereClause, $interval, $days) {
    $query = "
      SELECT
        DATE_FORMAT(run_time, $interval) as period,
        SUM(CASE WHEN data LIKE '%error%' OR data LIKE '%failed%' THEN 1 ELSE 0 END) as errors
      FROM civicrm_job_log
      WHERE description like 'Finished execution%' AND run_time >= DATE_SUB(NOW(), INTERVAL $days DAY) $whereClause
      GROUP BY DATE_FORMAT(run_time, $interval)
      ORDER BY period
    ";

    return $this->executeChartQuery($query);
  }

  /**
   * Get success rate chart data
   */
  private function getSuccessRateChartData($whereClause, $interval, $days) {
    $query = "
      SELECT
        DATE_FORMAT(run_time, $interval) as period,
        ROUND(
          (SUM(CASE WHEN data NOT LIKE '%error%' AND data NOT LIKE '%failed%' THEN 1 ELSE 0 END) / COUNT(*)) * 100,
          1
        ) as success_rate
      FROM civicrm_job_log
      WHERE description like 'Finished execution%' AND run_time >= DATE_SUB(NOW(), INTERVAL $days DAY) $whereClause
      GROUP BY DATE_FORMAT(run_time, $interval)
      ORDER BY period
    ";

    return $this->executeChartQuery($query);
  }

  /**
   * Execute chart query and return formatted data
   */
  private function executeChartQuery($query) {
    $dao = CRM_Core_DAO::executeQuery($query);
    $data = [];

    while ($dao->fetch()) {
      $data[] = [
        'period' => $dao->period,
        'value' => isset($dao->executions) ? $dao->executions :
          (isset($dao->avg_duration) ? round($dao->avg_duration, 2) :
            (isset($dao->errors) ? $dao->errors :
              (isset($dao->success_rate) ? $dao->success_rate : 0))),
      ];
    }

    return $data;
  }

  /**
   * Build WHERE clause for filtering
   * @param array $filters
   * @return string
   */
  private function buildWhereClause($filters = [], $tablePrefix = '') {
    $conditions = [];
    if (!empty($tablePrefix)) {
      $tablePrefix = $tablePrefix . '.';
    }
    $conditions[] = "{$tablePrefix}domain_id = " . $this->_domain_id;
    if (!empty($filters['job_id'])) {
      $jobId = (int)$filters['job_id'];
      $conditions[] = "{$tablePrefix}job_id = $jobId";
    }

    if (!empty($filters['start_date'])) {
      $startDate = CRM_Utils_Type::escape($filters['start_date'], 'String');
      $conditions[] = "{$tablePrefix}run_time >= '$startDate'";
    }

    if (!empty($filters['end_date'])) {
      $endDate = CRM_Utils_Type::escape($filters['end_date'], 'String');
      $conditions[] = "{$tablePrefix}run_time <= '$endDate'";
    }

    $dateRange = $this->getDateRange($filters);
    if ($dateRange) {
      $conditions[] = "{$tablePrefix}run_time >= '" . $dateRange['start'] . "'";
      $conditions[] = "{$tablePrefix}run_time <= '" . $dateRange['end'] . "'";
    }

    if (!empty($filters['status'])) {
      if ($filters['status'] === 'success') {
        $conditions[] = "(data NOT LIKE '%error%' AND data NOT LIKE '%failed%')";
      }
      elseif ($filters['status'] === 'error') {
        $conditions[] = "(data LIKE '%error%' OR data LIKE '%failed%')";
      }
    }

    return $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
  }

  /**
   * Get appropriate interval based on number of days
   */
  private function getIntervalByDays($days) {
    if ($days <= 7) {
      return '"%Y-%m-%d-%H"'; // Hourly data for week view
    }
    elseif ($days <= 90) {
      return '"%Y-%m-%d"'; // Daily data for month/quarter view
    }
    else {
      return '"%Y-%m-%d"'; // Weekly data for year view
    }
  }

  /**
   * Get date range based on various input formats
   *
   * @param array $params Input parameters
   * @return array|null Array with 'start' and 'end' dates or null
   */
  private function getDateRange($params = []) {
    $days = $params['days'] ?? 0;

    // Validate days input
    if ($days <= 0) {
      $days = 30; // Default fallback
    }

    // Limit maximum range to prevent performance issues
    if ($days > 3650) { // 10 years max
      $days = 3650;
    }

    $endDate = new DateTime();
    $startDate = new DateTime();
    $startDate->sub(new DateInterval("P{$days}D"));

    return [
      'start' => $startDate->format('Y-m-d 00:00:00'),
      'end' => $endDate->format('Y-m-d 23:59:59'),
      'days' => $days,
    ];
    return $dateRange;
  }

  /**
   * Determine job status based on recent performance
   */
  private function determineJobStatus($errorCount, $totalCount, $isActive) {
    if (!$isActive) {
      return 'inactive';
    }

    if ($totalCount == 0) {
      return 'unknown';
    }

    $errorRate = ($errorCount / $totalCount) * 100;

    if ($errorRate == 0) {
      return 'success';
    }
    elseif ($errorRate <= 5) {
      return 'warning';
    }
    else {
      return 'error';
    }
  }

  /**
   * Determine execution status from job log data
   */
  private function determineExecutionStatus($data) {
    if (empty($data)) {
      return 'unknown';
    }

    $data = strtolower($data);

    if (strpos($data, 'failed') !== FALSE || strpos($data, 'exception') !== FALSE) {
      return 'error';
    }
    elseif (strpos($data, 'warning') !== FALSE) {
      return 'warning';
    }
    else {
      return 'success';
    }
  }

  /**
   * Extract duration from job log data
   */
  private function extractDurationFromData($data) {
    if (preg_match('/duration:\s*([0-9.]+)/', $data, $matches)) {
      return (float)$matches[1];
    }

    if (preg_match('/took\s+([0-9.]+)\s*seconds?/', $data, $matches)) {
      return (float)$matches[1];
    }

    if (preg_match('/execution time:\s*([0-9.]+)/', $data, $matches)) {
      return (float)$matches[1];
    }

    return 0;
  }

  /**
   * Get date range options for dropdown
   */
  private function getDateRangeOptions() {
    return [
      '1' => ts('Last 1 days'),
      '7' => ts('Last 7 days'),
      '30' => ts('Last 30 days'),
      '90' => ts('Last 90 days'),
      '365' => ts('Last year'),
      'custom' => ts('Custom range'),
    ];
  }

  /**
   * AJAX endpoint for fetching updated statistics
   */
  public static function getStatsAjax() {
    $filters = [];

    if (!empty($_POST['job_id'])) {
      $filters['job_id'] = $_POST['job_id'];
    }

    if (!empty($_POST['start_date'])) {
      $filters['start_date'] = $_POST['start_date'];
    }

    if (!empty($_POST['end_date'])) {
      $filters['end_date'] = $_POST['end_date'];
    }

    if (!empty($_POST['days'])) {
      $filters['days'] = (int)$_POST['days'];
    }

    $page = new self();
    $stats = $page->getJobStatistics($filters);

    CRM_Utils_JSON::output($stats);
  }

  /**
   * AJAX endpoint for fetching chart data
   */
  public static function getChartDataAjax() {
    $chartType = CRM_Utils_Request::retrieve('chart_type', 'String');
    $filters = [];

    if (!empty($_POST['job_id'])) {
      $filters['job_id'] = $_POST['job_id'];
    }

    if (!empty($_POST['start_date'])) {
      $filters['start_date'] = $_POST['start_date'];
    }

    if (!empty($_POST['end_date'])) {
      $filters['end_date'] = $_POST['end_date'];
    }

    if (!empty($_POST['days'])) {
      $filters['days'] = (int)$_POST['days'];
    }

    $page = new self();
    $chartData = $page->getChartData($chartType, $filters);

    CRM_Utils_JSON::output($chartData);
  }

  /**
   * AJAX endpoint for fetching job list
   */
  public static function getJobListAjax() {
    $page = new self();
    $jobList = $page->getJobList();

    CRM_Utils_JSON::output($jobList);
  }

  /**
   * AJAX endpoint for fetching recent executions
   */
  public static function getRecentExecutionsAjax() {
    $limit = CRM_Utils_Request::retrieve('limit', 'Integer', NULL, FALSE, 50);
    $page = new self();
    $executions = $page->getRecentExecutions($limit);

    CRM_Utils_JSON::output($executions);
  }

  /**
   * @param $executionId
   * @return array|string[]
   * @throws CRM_Core_Exception
   * @throws \Civi\Core\Exception\DBQueryException
   */
  public function getExecutionDetails($executionId) {
    $executionId = CRM_Utils_Request::retrieve('execution_id', 'Integer');

    if (!$executionId) {
      return ['error' => 'Missing execution ID'];
    }
    $query = "
      SELECT
        jl.*,
        j.name as job_name,
        j.api_entity,
        j.api_action,
        j.description as job_description
      FROM civicrm_job_log jl
      INNER JOIN civicrm_job j ON jl.job_id = j.id
      WHERE jl.id = %1 AND domain_id = {$this->_domain_id}
    ";

    $dao = CRM_Core_DAO::executeQuery($query, [
      1 => [$executionId, 'Integer']
    ]);

    if ($dao->fetch()) {
      $details = [
        'id' => $dao->id,
        'job_id' => $dao->job_id,
        'job_name' => $dao->job_name ?: $dao->name,
        'api_call' => $dao->api_entity . '.' . $dao->api_action,
        'command' => $dao->command,
        'description' => $dao->description,
        'job_description' => $dao->job_description,
        'run_time' => $dao->run_time,
        'data' => $dao->data,
        'formatted_data' => self::formatJobLogData($dao->data)
      ];

      return $details;
    }
    else {
      return ['error' => 'Execution not found'];
    }
  }

  /**
   * Format job log data for display
   */
  private static function formatJobLogData($data) {
    if (empty($data)) {
      return ['message' => 'No data available'];
    }

    // Try to parse as JSON first
    $json = json_decode($data, TRUE);
    if ($json !== NULL) {
      return $json;
    }

    // If not JSON, try to extract key information
    $formatted = [];

    // Look for common patterns
    if (preg_match('/duration:\s*([0-9.]+)/', $data, $matches)) {
      $formatted['duration'] = $matches[1] . ' seconds';
    }

    if (preg_match('/memory:\s*([0-9.]+[KMG]?B?)/', $data, $matches)) {
      $formatted['memory_usage'] = $matches[1];
    }

    if (preg_match('/processed:\s*([0-9,]+)/', $data, $matches)) {
      $formatted['records_processed'] = $matches[1];
    }

    if (preg_match('/error/i', $data)) {
      $formatted['status'] = 'error';
      $formatted['has_errors'] = TRUE;
    }
    elseif (preg_match('/warning/i', $data)) {
      $formatted['status'] = 'warning';
      $formatted['has_warnings'] = TRUE;
    }
    else {
      $formatted['status'] = 'success';
    }

    // Add raw data
    $formatted['raw_data'] = $data;

    return $formatted;
  }
}
