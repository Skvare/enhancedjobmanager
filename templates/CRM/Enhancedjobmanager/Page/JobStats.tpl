<div class="crm-block crm-content-block crm-job-stats-block">

  {* Page Header *}
  <div class="job-stats-header">
    <h1 class="page-title">
      <i class="crm-i fa-chart-bar" aria-hidden="true"></i>
      {ts}Job Log Statistics{/ts}
    </h1>
    <p class="page-description">
      {ts}Monitor and analyze your CiviCRM scheduled jobs performance{/ts}
    </p>
  </div>
  <div>
  <a href="{crmURL p='civicrm/admin/job' q='reset=1'}" class="crm-modern-button crm-modern-button-primary">
    <i class="crm-i fa-list-alt" aria-hidden="true"></i>
    {ts}Back to Job Listing{/ts}
  </a>
  </div>
  {* Control Panel *}
  <div class="job-stats-controls">
    <div class="crm-section">
      <div class="crm-form-block">
        <table class="form-layout">
          <tr>
            <td class="label">
              <label for="job-select">{ts}Select Job{/ts}</label>
            </td>
            <td>
              <select id="job-select" name="job_id" class="crm-select2">
                <option value="">{ts}All Jobs{/ts}</option>
                {foreach from=$jobList item=job}
                  <option value="{$job.id}">{$job.name}</option>
                {/foreach}
              </select>
            </td>
            <td class="label">
              <label for="date-range">{ts}Date Range{/ts}</label>
            </td>
            <td>
              <select id="date-range" name="date_range" class="crm-select2">
                {foreach from=$dateRanges key=value item=label}
                  <option value="{$value}" {if $value == 30}selected{/if}>{$label}</option>
                {/foreach}
              </select>
            </td>
            <td>
              <button type="button" id="refresh-stats" class="crm-button crm-button-type-refresh">
                <i class="crm-i fa-sync" aria-hidden="true"></i>
                {ts}Refresh{/ts}
              </button>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>

  {* Statistics Cards *}
  <div class="job-stats-summary">
    <div class="stats-grid">
      <div class="stat-card" id="total-executions-card">
        <div class="stat-icon">
          <i class="crm-i fa-play-circle" role="img" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value" id="total-executions">{$jobStats.total_executions|default:0}</div>
          <div class="stat-label">{ts}Total Executions{/ts}</div>
          <div class="stat-trend" id="executions-trend"></div>
        </div>
      </div>

      <div class="stat-card" id="success-rate-card">
        <div class="stat-icon success">
          <i class="crm-i fa-check-circle" role="img" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value" id="success-rate">{$jobStats.success_rate|default:0}%</div>
          <div class="stat-label">{ts}Success Rate{/ts}</div>
          <div class="stat-trend" id="success-trend"></div>
        </div>
      </div>

      <div class="stat-card" id="error-rate-card">
        <div class="stat-icon error">
          <i class="crm-i fa-exclamation-circle" role="img" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value" id="error-rate">{$jobStats.error_rate|default:0}%</div>
          <div class="stat-label">{ts}Error Rate{/ts}</div>
          <div class="stat-trend" id="error-trend"></div>
        </div>
      </div>

      <div class="stat-card" id="avg-duration-card">
        <div class="stat-icon">
          <i class="crm-i fa-clock" role="img" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
          <div class="stat-value" id="avg-duration">{$jobStats.avg_duration|default:0}s</div>
          <div class="stat-label">{ts}Avg Duration{/ts}</div>
          <div class="stat-trend" id="duration-trend"></div>
        </div>
      </div>
    </div>
  </div>

  {* Chart Section *}
  <div class="job-stats-chart">
    <div class="crm-section">
      <div class="crm-section-header">
        <h3>{ts}Performance Overview{/ts}</h3>
        <div class="chart-controls">
          <div class="chart-tabs">
            <button type="button" class="chart-tab active" data-chart="executions">
              {ts}Executions{/ts}
            </button>
            <button type="button" class="chart-tab" data-chart="duration">
              {ts}Duration{/ts}
            </button>
            <button type="button" class="chart-tab" data-chart="errors">
              {ts}Errors{/ts}
            </button>
            <button type="button" class="chart-tab" data-chart="success">
              {ts}Success Rate{/ts}
            </button>
          </div>
        </div>
      </div>
      <div class="chart-container">
        <canvas id="performance-chart"></canvas>
      </div>
    </div>
  </div>

  {* Job List Table *}
  <div class="job-list-section">
    <div class="crm-section">
      <div class="crm-section-header">
        <h3>{ts}Job Performance Summary{/ts}</h3>
      </div>
      <div class="crm-form-block">
        <div id="job-list-loading" class="crm-loading-element" style="display: none;">
          {ts}Loading job data...{/ts}
        </div>
        <table id="job-performance-table" class="display" cellspacing="0" width="100%">
          <thead>
          <tr>
            <th>{ts}Job Name{/ts}</th>
            <th>{ts}Status{/ts}</th>
            <th>{ts}API Call{/ts}</th>
            <th>{ts}Last Run{/ts}</th>
            <th>{ts}Next Run{/ts}</th>
            <th>{ts}Executions (30d){/ts}</th>
            <th>{ts}Error Rate{/ts}</th>
            <th>{ts}Actions{/ts}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$jobList item=job}
            <tr data-job-id="{$job.id}">
              <td>
                <strong>{$job.name}</strong>
                {if $job.description}
                  <br><small class="description">{$job.description|truncate:100}</small>
                {/if}
              </td>
              <td>
                  <span class="crm-status-{$job.status}">
                    {if $job.status == 'success'}
                      <i class="crm-i fa-check-circle" role="img" aria-hidden="true"></i> {ts}Healthy{/ts}
                    {elseif $job.status == 'warning'}
                      <i class="crm-i fa-exclamation-triangle" role="img" aria-hidden="true"></i> {ts}Warning{/ts}
                    {elseif $job.status == 'error'}
                      <i class="crm-i fa-times-circle" role="img" aria-hidden="true"></i> {ts}Error{/ts}
                    {else}
                      <i class="crm-i fa-question-circle" role="img" aria-hidden="true"></i> {ts}Unknown{/ts}
                    {/if}
                  </span>
              </td>
              <td>
                <code>{$job.api_call}</code>
              </td>
              <td>
                {if $job.last_run}
                  {$job.last_run|crmDate}
                {else}
                  <em>{ts}Never{/ts}</em>
                {/if}
              </td>
              <td>
                {if $job.next_run}
                  {$job.next_run|crmDate}
                {else}
                  <em>{ts}Not scheduled{/ts}</em>
                {/if}
              </td>
              <td class="text-center">
                {$job.execution_count|default:0}
              </td>
              <td class="text-center">
                  <span class="error-rate {if $job.error_rate > 10}high-error{elseif $job.error_rate > 5}medium-error{else}low-error{/if}">
                    {$job.error_rate}%
                  </span>
              </td>
              <td>
                <div class="crm-submit-buttons">
                  <a href="{crmURL p='civicrm/admin/job' q="action=update&id=`$job.id`&reset=1"}"
                     class="crm-button crm-button-small" title="{ts}Edit Job{/ts}">
                    <i class="crm-i fa-edit" role="img" aria-hidden="true"></i>
                  </a>
                </div>
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {* Recent Executions *}
  <div class="recent-executions-section">
    <div class="crm-section">
      <div class="crm-section-header">
        <h3>{ts}Recent Job Executions{/ts}</h3>
        <div class="section-actions">
          <button type="button" id="refresh-executions" class="crm-button crm-button-small">
            <i class="crm-i fa-sync" role="img" aria-hidden="true"></i> {ts}Refresh{/ts}
          </button>
        </div>
      </div>
      <div class="crm-form-block">
        <div id="executions-loading" class="crm-loading-element" style="display: none;">
          {ts}Loading recent executions...{/ts}
        </div>
        <table id="recent-executions-table" class="display" cellspacing="0" width="100%">
          <thead>
          <tr>
            <th>{ts}Job Name{/ts}</th>
            <th>{ts}Status{/ts}</th>
            <th>{ts}Run Time{/ts}</th>
            <th>{ts}Duration{/ts}</th>
            <th>{ts}Health{/ts}</th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$recentExecutions item=execution}
            <tr data-execution-id="{$execution.id}" class="execution-{$execution.status}">
              <td>{$execution.job_name}</td>
              <td>
                  <span class="crm-status-{$execution.status}">
                    {if $execution.status == 'RUNNING'}
                      <i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> {ts}Running{/ts}
                    {elseif $execution.status == 'COMPLETED'}
                      <i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> {ts}Success{/ts}
                    {elseif $execution.status == 'LONG_RUNNING' OR $execution.status == 'STUCK_OR_FAILED' OR $execution.status == 'NEVER_RUN'}
                      <i class="crm-i fa-exclamation-triangle text-warning" role="img" aria-hidden="true"></i> {ts}Warning{/ts}
                    {elseif $execution.status == 'DATA_ERROR'}
                      <i class="crm-i fa-times-circle text-danger" role="img" aria-hidden="true"></i> {ts}Error{/ts}
                    {else}
                      <i class="crm-i fa-question-circle text-muted" role="img" aria-hidden="true"></i> {ts}Unknown{/ts}
                    {/if}
                  </span>
              </td>
              <td>{$execution.run_time|crmDate}</td>
              <td>
                {if $execution.duration}
                  {$execution.duration}s
                {else}
                  <em>{ts}N/A{/ts}</em>
                {/if}
              </td>
              <td>
                {if $execution.health}
                  <em class="small">{$execution.health}</em>
                {/if}
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

{* Job Log Details Modal *}
<div id="job-log-modal" class="crm-container" style="display: none;">
  <div class="modal-header">
    <h3 id="modal-title">{ts}Job Execution Details{/ts}</h3>
    <button type="button" class="close-modal">&times;</button>
  </div>
  <div class="modal-body">
    <div id="modal-content">
      <div class="loading">
        <i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i>
        {ts}Loading details...{/ts}
      </div>
    </div>
  </div>
</div>

{* Include JavaScript *}
{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      var jobStatsManager = {
        // Chart instance
        chart: null,
        currentChartType: 'executions',

        // Initialize the page
        init: function() {
          this.initChart();
          this.bindEvents();
          this.initDataTables();
          this.loadData();
        },

        // Initialize Chart.js
        initChart: function() {
          var ctx = document.getElementById('performance-chart').getContext('2d');
          this.chart = new Chart(ctx, {
            type: 'line',
            data: {
              labels: [],
              datasets: [{
                label: 'Data',
                data: [],
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                fill: true,
                tension: 0.4
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  display: false
                }
              },
              scales: {
                x: {
                  grid: {
                    display: false
                  }
                },
                y: {
                  grid: {
                    color: '#f3f4f6'
                  }
                }
              }
            }
          });
        },

        // Bind event handlers
        bindEvents: function() {
          var self = this;

          // Chart tab switching
          $('.chart-tab').on('click', function() {
            $('.chart-tab').removeClass('active');
            $(this).addClass('active');
            self.currentChartType = $(this).data('chart');
            self.loadChartData();
          });

          // Refresh button
          $('#refresh-stats').on('click', function() {
            self.loadData();
          });

          // Filter changes
          $('#job-select, #date-range').on('change', function() {
            self.loadData();
          });

          // Custom date inputs
          $('#custom-start, #custom-end').on('change', function() {
            if ($('#custom-start').val() && $('#custom-end').val()) {
              $('#date-range').val('custom');
              self.loadData();
            }
          });

          // View job logs
          $(document).on('click', '.view-job-logs', function() {
            var jobId = $(this).data('job-id');
            self.showJobLogs(jobId);
          });

          // View execution details
          $(document).on('click', '.view-execution-details', function() {
            var executionId = $(this).data('execution-id');
            self.showExecutionDetails(executionId);
          });

          // Modal close
          $(document).on('click', '.close-modal', function() {
            $('#job-log-modal').hide();
          });

          // Refresh executions
          $('#refresh-executions').on('click', function() {
            self.loadRecentExecutions();
          });
        },

        // Initialize DataTables
        initDataTables: function() {
          $('#job-performance-table').DataTable({
            pageLength: 25,
            order: [[6, 'desc']], // Order by error rate desc
            columnDefs: [
              { targets: [5, 6], className: 'text-center' },
              { targets: [7], orderable: false }
            ]
          });

          $('#recent-executions-table').DataTable({
            pageLength: 50,
            order: [[2, 'desc']], // Order by run time desc
            columnDefs: [
              { targets: [3], className: 'text-center' },
              { targets: [4], orderable: false }
            ]
          });
        },

        // Load all data
        loadData: function() {
          this.loadStats();
          this.loadChartData();
          this.loadJobList();
          this.loadRecentExecutions();
        },

        // Load statistics
        loadStats: function() {
          var filters = this.getFilters();

          CRM.api3('JobLogStats', 'get', filters).done(function(result) {
            if (result.values) {
              $('#total-executions').text(result.values.total_executions.toLocaleString());
              $('#success-rate').text(result.values.success_rate + '%');
              $('#error-rate').text(result.values.error_rate + '%');
              $('#avg-duration').text(result.values.avg_duration + 's');
            }
          });
        },

        // Load chart data
        loadChartData: function() {
          var self = this;
          var filters = this.getFilters();
          filters.chart_type = this.currentChartType;

          CRM.api3('JobLogStats', 'chart', filters).done(function(result) {
            if (result.values && result.values.length > 0) {
              var labels = result.values.map(function(item) {
                return new Date(item.period).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
              });
              var data = result.values.map(function(item) {
                return item.value;
              });

              self.chart.data.labels = labels;
              self.chart.data.datasets[0].data = data;
              self.chart.data.datasets[0].label = self.getChartLabel(self.currentChartType);
              self.chart.update();
            }
          });
        },

        // Load job list
        loadJobList: function() {
          $('#job-list-loading').show();

          CRM.api3('JobLogStats', 'performance').done(function(result) {
            // Update job performance table
            var table = $('#job-performance-table').DataTable();
            table.clear();

            if (result.values) {
              result.values.forEach(function(job) {
                var statusIcon = '';
                switch(job.status) {
                  case 'success':
                    statusIcon = '<i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> Healthy';
                    break;
                  case 'warning':
                    statusIcon = '<i class="crm-i fa-exclamation-triangle text-warning" role="img" aria-hidden="true"></i> Warning';
                    break;
                  case 'error':
                    statusIcon = '<i class="crm-i fa-times-circle text-danger" role="img" aria-hidden="true"></i> Error';
                    break;
                  default:
                    statusIcon = '<i class="crm-i fa-question-circle text-muted" role="img" aria-hidden="true"></i> Unknown';
                }

                var lastRun = job.last_run ? new Date(job.last_run).toLocaleString() : 'Never';
                var nextRun = job.next_run ? job.next_run : 'Not scheduled';

                table.row.add([
                  '<strong>' + job.name + '</strong>' + (job.description ? '<br><small>' + job.description.substring(0, 100) + '</small>' : ''),
                  statusIcon,
                  '<code>' + job.api_call + '</code>',
                  lastRun,
                  nextRun,
                  job.execution_count || 0,
                  '<span class="error-rate ' + (job.error_rate > 10 ? 'high-error' : job.error_rate > 5 ? 'medium-error' : 'low-error') + '">' + job.error_rate + '%</span>',
                  '<div class="crm-submit-buttons"><a href="'+ CRM.url('civicrm/admin/job', {action: 'update', id: job.id, reset: 1}) + '"class="crm-button crm-button-small" title="{ts}Edit Job{/ts}"> <i class="crm-i fa-edit" role="img" aria-hidden="true"></i></a> </div>'
                ]);
              });
            }

            table.draw();
            $('#job-list-loading').hide();
          });
        },

        // Load recent executions
        loadRecentExecutions: function() {
          $('#executions-loading').show();

          CRM.api3('JobLogStats', 'recentexecutions').done(function(result) {
            var table = $('#recent-executions-table').DataTable();
            table.clear();

            if (result.values) {
              result.values.forEach(function(execution) {
                var statusIcon = '';
                switch(execution.status) {
                  case 'COMPLETED':
                    statusIcon = '<i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> Success';
                    break;
                  case 'RUNNING':
                    statusIcon = '<i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> Running';
                    break;
                  case 'LONG_RUNNING':
                  case 'STUCK_OR_FAILED':
                  case 'NEVER_RUN':
                    statusIcon = '<i class="crm-i fa-exclamation-triangle text-warning" role="img" aria-hidden="true"></i> Warning';
                    break;
                  case 'DATA_ERROR':
                    statusIcon = '<i class="crm-i fa-times-circle text-danger" role="img" aria-hidden="true"></i> Error';
                    break;
                  default:
                    statusIcon = '<i class="crm-i fa-question-circle text-muted" role="img" aria-hidden="true"></i> Unknown';
                }

                var runTime = new Date(execution.run_time).toLocaleString();
                var duration = execution.duration ? execution.duration + 's' : 'N/A';
                var health = execution.health ? execution.health : 'N/A';

                table.row.add([
                  execution.job_name,
                  statusIcon,
                  runTime,
                  duration,
                  '<em class="small">' + health + '</em>'
                ]);
              });
            }

            table.draw();
            $('#executions-loading').hide();
          });
        },

        // Get current filters
        getFilters: function() {
          var filters = {};

          var jobId = $('#job-select').val();
          if (jobId) {
            filters.job_id = jobId;
          }

          var dateRange = $('#date-range').val();
          if (dateRange === 'custom') {
            var startDate = $('#custom-start').val();
            var endDate = $('#custom-end').val();
            if (startDate) filters.start_date = startDate;
            if (endDate) filters.end_date = endDate;
          } else {
            filters.days = parseInt(dateRange);
          }

          return filters;
        },

        // Get chart label
        getChartLabel: function(chartType) {
          switch(chartType) {
            case 'executions': return 'Job Executions';
            case 'duration': return 'Average Duration (seconds)';
            case 'errors': return 'Error Count';
            case 'success': return 'Success Rate (%)';
            default: return 'Data';
          }
        },

        // Show job logs modal
        showJobLogs: function(jobId) {
          $('#modal-title').text('Job Logs');
          $('#modal-content').html('<div class="loading"><i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i> Loading job logs...</div>');
          $('#job-log-modal').show();

          // Load job logs via API
          // Implementation would depend on your specific job log API
        },

        // Show execution details modal
        showExecutionDetails: function(executionId) {
          $('#modal-title').text('Execution Details');
          $('#modal-content').html('<div class="loading"><i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i> Loading execution details...</div>');
          $('#job-log-modal').show();

          // Load execution details via API
          // Implementation would depend on your specific execution details API
        }
      };

      // Initialize when page loads
      jobStatsManager.init();
    });
  </script>
{/literal}

