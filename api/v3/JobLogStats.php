<?php

/**
 * API wrapper for job log statistics
 * File: api/v3/JobLogStats.php
 */

/**
 * Get job log statistics
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_job_log_stats_get($params) {
  $page = new CRM_Enhancedjobmanager_Page_JobStats();
  $stats = $page->getJobStatistics($params);

  return civicrm_api3_create_success($stats, $params);
}

/**
 * Get chart data for job logs
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_job_log_stats_chart($params) {
  $chartType = $params['chart_type'] ?? 'executions';
  $page = new CRM_Enhancedjobmanager_Page_JobStats();
  $chartData = $page->getChartData($chartType, $params);

  return civicrm_api3_create_success($chartData, $params);
}

/**
 * Get job performance summary
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_job_log_stats_performance($params) {
  $page = new CRM_Enhancedjobmanager_Page_JobStats();
  $jobList = $page->getJobList();

  return civicrm_api3_create_success($jobList, $params);
}

/**
 * Get job performance summary
 *
 * @param array $params
 * @return array
 */
function civicrm_api3_job_log_stats_recentexecutions($params) {
  $page = new CRM_Enhancedjobmanager_Page_JobStats();
  $executions = $page->getRecentExecutions(CRM_Utils_Request::retrieve('limit', 'Integer', NULL, FALSE, 50));

  return civicrm_api3_create_success($executions, $params);
}
