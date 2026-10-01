<?php
use CRM_Enhancedjobmanager_ExtensionUtil as E;

class CRM_Enhancedjobmanager_Page_JobStatsAjax {

  /**
   * Get job statistics data
   */
  public static function getStats() {
    $filters = self::getFiltersFromRequest();
    $page = new CRM_Enhancedjobmanager_Page_JobStats();
    $stats = $page->getJobStatistics($filters);

    CRM_Utils_JSON::output($stats);
  }

  /**
   * Get chart data
   */
  public static function getChartData() {
    $chartType = CRM_Utils_Request::retrieve('chart_type', 'String');
    $filters = self::getFiltersFromRequest();

    $page = new CRM_Enhancedjobmanager_Page_JobStats();
    $chartData = $page->getChartData($chartType, $filters);

    CRM_Utils_JSON::output([
      'labels' => array_column($chartData, 'period'),
      'data' => array_column($chartData, 'value')
    ]);
  }

  /**
   * Get job list data
   */
  public static function getJobList() {
    $page = new CRM_Enhancedjobmanager_Page_JobStats();
    $jobList = $page->getJobList();

    CRM_Utils_JSON::output($jobList);
  }

  /**
   * Get recent executions
   */
  public static function getRecentExecutions() {
    $limit = CRM_Utils_Request::retrieve('limit', 'Integer', NULL, FALSE, 50);
    $page = new CRM_Enhancedjobmanager_Page_JobStats();
    $executions = $page->getRecentExecutions($limit);

    CRM_Utils_JSON::output($executions);
  }

  /**
   * Get job execution details
   */
  public static function getExecutionDetails() {
    $executionId = CRM_Utils_Request::retrieve('execution_id', 'Integer');
    $page = new CRM_Enhancedjobmanager_Page_JobStats();
    $executionDetails = $page->getExecutionDetails($executionId);
    CRM_Utils_JSON::output($executionDetails);
  }

  /**
   * Get filters from request
   */
  private static function getFiltersFromRequest() {
    $filters = [];

    $jobId = CRM_Utils_Request::retrieve('job_id', 'Integer');
    if ($jobId) {
      $filters['job_id'] = $jobId;
    }

    $startDate = CRM_Utils_Request::retrieve('start_date', 'String');
    if ($startDate) {
      $filters['start_date'] = $startDate;
    }

    $endDate = CRM_Utils_Request::retrieve('end_date', 'String');
    if ($endDate) {
      $filters['end_date'] = $endDate;
    }

    $days = CRM_Utils_Request::retrieve('days', 'Integer');
    if ($days) {
      $filters['days'] = $days;
    }

    return $filters;
  }

}
