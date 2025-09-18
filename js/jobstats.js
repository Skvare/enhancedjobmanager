/**
 * Enhanced Job Manager - Job Statistics JavaScript
 * File: js/jobstats.js
 */

(function($) {
  'use strict';

  var JobStatsManager = {
    // Configuration
    config: {
      autoRefresh: false,
      refreshInterval: 300000, // 5 minutes
      chartColors: {
        executions: '#4f46e5',
        duration: '#10b981',
        errors: '#ef4444',
        success: '#059669'
      },
      apiEndpoints: {
        stats: '/civicrm/admin/job/stats/ajax/getstats',
        chartData: '/civicrm/admin/job/stats/ajax/getchartdata',
        jobList: '/civicrm/admin/job/stats/ajax/getjoblist',
        recentExecutions: '/civicrm/admin/job/stats/ajax/getrecentexecutions',
        executionDetails: '/civicrm/admin/job/stats/ajax/getexecutiondetails'
      }
    },

    // State
    state: {
      chart: null,
      currentChartType: 'executions',
      filters: {},
      refreshTimer: null,
      isLoading: false
    },

    // Initialize the manager
    init: function() {
      this.loadSettings();
      this.initializeChart();
      this.bindEvents();
      this.initializeDataTables();
      this.loadInitialData();
      this.setupAutoRefresh();
    },

    // Load user settings
    loadSettings: function() {
      var savedSettings = localStorage.getItem('civicrm_job_stats_settings');
      if (savedSettings) {
        try {
          var settings = JSON.parse(savedSettings);
          this.config = $.extend(true, this.config, settings);
        } catch (e) {
          console.warn('Failed to load saved settings:', e);
        }
      }
    },

    // Save user settings
    saveSettings: function() {
      try {
        localStorage.setItem('civicrm_job_stats_settings', JSON.stringify(this.config));
      } catch (e) {
        console.warn('Failed to save settings:', e);
      }
    },

    // Initialize Chart.js
    initializeChart: function() {
      var ctx = document.getElementById('performance-chart');
      if (!ctx) return;

      this.state.chart = new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
          labels: [],
          datasets: [{
            label: 'Data',
            data: [],
            borderColor: this.config.chartColors[this.state.currentChartType],
            backgroundColor: this.config.chartColors[this.state.currentChartType] + '20',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: this.config.chartColors[this.state.currentChartType],
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleColor: '#ffffff',
              bodyColor: '#ffffff',
              cornerRadius: 8,
              displayColors: false,
              callbacks: {
                title: function(context) {
                  return 'Date: ' + context[0].label;
                },
                label: function(context) {
                  var value = context.parsed.y;
                  var suffix = JobStatsManager.getChartValueSuffix(JobStatsManager.state.currentChartType);
                  return JobStatsManager.getChartLabel(JobStatsManager.state.currentChartType) + ': ' + value + suffix;
                }
              }
            }
          },
          scales: {
            x: {
              grid: {
                display: false
              },
              ticks: {
                color: '#6b7280',
                font: {
                  size: 12
                }
              }
            },
            y: {
              grid: {
                color: '#f3f4f6'
              },
              ticks: {
                color: '#6b7280',
                font: {
                  size: 12
                },
                callback: function(value) {
                  var suffix = JobStatsManager.getChartValueSuffix(JobStatsManager.state.currentChartType);
                  return value + suffix;
                }
              }
            }
          },
          elements: {
            point: {
              hoverBackgroundColor: '#ffffff'
            }
          },
          animation: {
            duration: 750,
            easing: 'easeInOutQuart'
          }
        }
      });
    },

    // Bind event handlers
    bindEvents: function() {
      var self = this;

      // Chart tab switching
      $(document).on('click', '.chart-tab', function() {
        $('.chart-tab').removeClass('active');
        $(this).addClass('active');
        self.state.currentChartType = $(this).data('chart');
        self.loadChartData();
      });

      // Filter changes
      $('#job-select, #date-range').on('change', function() {
        self.updateFilters();
        self.loadData();
      });

      // Custom date inputs
      $('#custom-start, #custom-end').on('change', function() {
        if ($('#custom-start').val() && $('#custom-end').val()) {
          $('#date-range').val('custom');
          self.updateFilters();
          self.loadData();
        }
      });

      // Refresh button
      $('#refresh-stats').on('click', function() {
        self.loadData();
      });

      // Auto-refresh toggle
      $(document).on('click', '#auto-refresh-toggle', function() {
        self.config.autoRefresh = $(this).is(':checked');
        self.saveSettings();
        self.setupAutoRefresh();
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
      $(document).on('click', '.close-modal, .modal-overlay', function(e) {
        if (e.target === this) {
          self.closeModal();
        }
      });

      // Keyboard shortcuts
      $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
          self.closeModal();
        } else if (e.key === 'r' && (e.ctrlKey || e.metaKey)) {
          e.preventDefault();
          self.loadData();
        }
      });

      // Export functionality
      $(document).on('click', '#export-data', function() {
        self.exportData();
      });

      // Settings modal
      $(document).on('click', '#settings-button', function() {
        self.showSettingsModal();
      });
    },

    // Initialize DataTables
    initializeDataTables: function() {
      if ($.fn.DataTable && $('#job-performance-table').length) {
        $('#job-performance-table').DataTable({
          pageLength: 25,
          order: [[6, 'desc']], // Order by error rate desc
          columnDefs: [
            { targets: [5, 6], className: 'text-center' },
            { targets: [7], orderable: false }
          ],
          language: {
            search: 'Search jobs:',
            lengthMenu: 'Show _MENU_ jobs per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ jobs',
            paginate: {
              previous: 'Previous',
              next: 'Next'
            }
          }
        });
      }

      if ($.fn.DataTable && $('#recent-executions-table').length) {
        $('#recent-executions-table').DataTable({
          pageLength: 50,
          order: [[2, 'desc']], // Order by run time desc
          columnDefs: [
            { targets: [3], className: 'text-center' },
            { targets: [4], orderable: false }
          ],
          language: {
            search: 'Search executions:',
            lengthMenu: 'Show _MENU_ executions per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ executions'
          }
        });
      }
    },

    // Load initial data
    loadInitialData: function() {
      this.updateFilters();
      this.loadData();
    },

    // Update filters from form
    updateFilters: function() {
      this.state.filters = {};

      var jobId = $('#job-select').val();
      if (jobId) {
        this.state.filters.job_id = jobId;
      }

      var dateRange = $('#date-range').val();
      if (dateRange === 'custom') {
        var startDate = $('#custom-start').val();
        var endDate = $('#custom-end').val();
        if (startDate) this.state.filters.start_date = startDate;
        if (endDate) this.state.filters.end_date = endDate;
      } else if (dateRange) {
        this.state.filters.days = parseInt(dateRange);
      }
    },

    // Load all data
    loadData: function() {
      if (this.state.isLoading) return;

      this.state.isLoading = true;
      this.showLoadingState();

      var promises = [
        this.loadStats(),
        this.loadChartData(),
        this.loadJobList(),
        this.loadRecentExecutions()
      ];

      Promise.all(promises).finally(() => {
        this.state.isLoading = false;
        this.hideLoadingState();
      });
    },

    // Load statistics
    loadStats: function() {
      return this.makeApiCall(this.config.apiEndpoints.stats, this.state.filters)
        .then(data => {
          this.updateStatCards(data);
        })
        .catch(error => {
          this.showError('Failed to load statistics: ' + error.message);
        });
    },

    // Load chart data
    loadChartData: function() {
      var params = $.extend({}, this.state.filters, {
        chart_type: this.state.currentChartType
      });

      return this.makeApiCall(this.config.apiEndpoints.chartData, params)
        .then(data => {
          this.updateChart(data);
        })
        .catch(error => {
          this.showError('Failed to load chart data: ' + error.message);
        });
    },

    // Load job list
    loadJobList: function() {
      return this.makeApiCall(this.config.apiEndpoints.jobList, this.state.filters)
        .then(data => {
          this.updateJobTable(data);
        })
        .catch(error => {
          this.showError('Failed to load job list: ' + error.message);
        });
    },

    // Load recent executions
    loadRecentExecutions: function() {
      return this.makeApiCall(this.config.apiEndpoints.recentExecutions, this.state.filters)
        .then(data => {
          this.updateExecutionsTable(data);
        })
        .catch(error => {
          this.showError('Failed to load recent executions: ' + error.message);
        });
    },

    // Make API call
    makeApiCall: function(endpoint, params) {
      return new Promise((resolve, reject) => {
        CRM.api3('JobLogStats', 'get', params || {})
          .done(function(result) {
            if (result.is_error) {
              reject(new Error(result.error_message));
            } else {
              resolve(result.values);
            }
          })
          .fail(function(xhr, status, error) {
            reject(new Error(error || 'API call failed'));
          });
      });
    },

    // Update statistic cards
    updateStatCards: function(data) {
      if (!data) return;

      $('#total-executions').text(this.formatNumber(data.total_executions || 0));
      $('#success-rate').text((data.success_rate || 0) + '%');
      $('#error-rate').text((data.error_rate || 0) + '%');
      $('#avg-duration').text((data.avg_duration || 0) + 's');

      // Update trend indicators (mock data for demo)
      this.updateTrendIndicator('#executions-trend', 'up', '+12.5%');
      this.updateTrendIndicator('#success-trend', data.success_rate > 95 ? 'up' : 'down',
        data.success_rate > 95 ? '+2.1%' : '-1.3%');
      this.updateTrendIndicator('#error-trend', data.error_rate < 5 ? 'down' : 'up',
        data.error_rate < 5 ? '-2.1%' : '+1.8%');
      this.updateTrendIndicator('#duration-trend', 'neutral', 'No change');
    },

    // Update trend indicator
    updateTrendIndicator: function(selector, direction, text) {
      var $trend = $(selector);
      $trend.removeClass('trend-up trend-down trend-neutral')
        .addClass('trend-' + direction)
        .text((direction === 'up' ? '↗ ' : direction === 'down' ? '↘ ' : '→ ') + text);
    },

    // Update chart
    updateChart: function(data) {
      if (!this.state.chart || !data) return;

      var chartColor = this.config.chartColors[this.state.currentChartType];

      this.state.chart.data.labels = data.labels || [];
      this.state.chart.data.datasets[0].data = data.data || [];
      this.state.chart.data.datasets[0].label = this.getChartLabel(this.state.currentChartType);
      this.state.chart.data.datasets[0].borderColor = chartColor;
      this.state.chart.data.datasets[0].backgroundColor = chartColor + '20';
      this.state.chart.data.datasets[0].pointBackgroundColor = chartColor;

      this.state.chart.update('smooth');
    },

    // Update job table
    updateJobTable: function(data) {
      if (!$.fn.DataTable || !data) return;

      var table = $('#job-performance-table').DataTable();
      table.clear();

      data.forEach(job => {
        var statusHtml = this.getStatusHtml(job.status);
        var lastRun = job.last_run ? this.formatDate(job.last_run) : '<em>Never</em>';
        var nextRun = job.next_run ? job.next_run : '<em>Not scheduled</em>';
        var errorRateClass = job.error_rate > 10 ? 'high-error' : job.error_rate > 5 ? 'medium-error' : 'low-error';

        table.row.add([
          '<strong>' + this.escapeHtml(job.name) + '</strong>' +
          (job.description ? '<br><small class="description">' + this.escapeHtml(job.description.substring(0, 100)) + '</small>' : ''),
          statusHtml,
          '<code>' + this.escapeHtml(job.api_call) + '</code>',
          lastRun,
          nextRun,
          this.formatNumber(job.execution_count || 0),
          '<span class="error-rate ' + errorRateClass + '">' + (job.error_rate || 0) + '%</span>',
          '<div class="action-buttons">' +
          '<button type="button" class="crm-button crm-button-small view-job-logs" data-job-id="' + job.id + '" title="View Logs">' +
          '<i class="crm-i fa-list" role="img" aria-hidden="true"></i>' +
          '</button>' +
          '<a href="' + CRM.url('civicrm/admin/job', {action: 'update', id: job.id, reset: 1}) + '" class="crm-button crm-button-small" title="Edit Job">' +
          '<i class="crm-i fa-edit" role="img" aria-hidden="true"></i>' +
          '</a>' +
          '</div>'
        ]);
      });

      table.draw();
    },

    // Update executions table
    updateExecutionsTable: function(data) {
      if (!$.fn.DataTable || !data) return;

      var table = $('#recent-executions-table').DataTable();
      table.clear();

      data.forEach(execution => {
        var statusHtml = this.getStatusHtml(execution.status);
        var runTime = this.formatDate(execution.run_time);
        var duration = execution.duration ? execution.duration + 's' : '<em>N/A</em>';
        var command = execution.command ?
          this.escapeHtml(execution.command.substring(0, 50)) :
          this.escapeHtml(execution.api_call);

        table.row.add([
          this.escapeHtml(execution.job_name),
          statusHtml,
          runTime,
          duration,
          '<code class="small">' + command + '</code>',
          '<button type="button" class="crm-button crm-button-small view-execution-details" data-execution-id="' + execution.id + '" title="View Details">' +
          '<i class="crm-i fa-eye" role="img" aria-hidden="true"></i>' +
          '</button>'
        ]);
      });

      table.draw();
    },

    // Get status HTML
    getStatusHtml: function(status) {
      var icons = {
        success: '<i class="crm-i fa-check-circle text-success" role="img" aria-hidden="true"></i> Success',
        warning: '<i class="crm-i fa-exclamation-triangle text-warning" role="img" aria-hidden="true"></i> Warning',
        error: '<i class="crm-i fa-times-circle text-danger" role="img" aria-hidden="true"></i> Error',
        unknown: '<i class="crm-i fa-question-circle text-muted" role="img" aria-hidden="true"></i> Unknown'
      };
      return '<span class="crm-status-' + status + '">' + (icons[status] || icons.unknown) + '</span>';
    },

    // Get chart label
    getChartLabel: function(chartType) {
      var labels = {
        executions: 'Job Executions',
        duration: 'Average Duration',
        errors: 'Error Count',
        success: 'Success Rate'
      };
      return labels[chartType] || 'Data';
    },

    // Get chart value suffix
    getChartValueSuffix: function(chartType) {
      var suffixes = {
        executions: '',
        duration: 's',
        errors: '',
        success: '%'
      };
      return suffixes[chartType] || '';
    },

    // Show job logs modal
    showJobLogs: function(jobId) {
      this.showModal('Job Logs', '<div class="loading"><i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i> Loading job logs...</div>');

      // In a real implementation, you would load job logs here
      setTimeout(() => {
        var content = '<p>Job logs for job ID: ' + jobId + '</p>';
        content += '<p><em>This would show detailed job execution logs in a real implementation.</em></p>';
        this.updateModalContent(content);
      }, 1000);
    },

    // Show execution details modal
    showExecutionDetails: function(executionId) {
      this.showModal('Execution Details', '<div class="loading"><i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i> Loading execution details...</div>');

      this.makeApiCall(this.config.apiEndpoints.executionDetails, {execution_id: executionId})
        .then(data => {
          var content = this.formatExecutionDetails(data);
          this.updateModalContent(content);
        })
        .catch(error => {
          this.updateModalContent('<div class="error">Failed to load execution details: ' + error.message + '</div>');
        });
    },

    // Format execution details
    formatExecutionDetails: function(data) {
      if (!data) return '<div class="error">No data available</div>';

      var html = '<div class="execution-details">';

      html += '<div class="detail-section">';
      html += '<h4>Basic Information</h4>';
      html += '<table class="detail-table">';
      html += '<tr><td><strong>Job Name:</strong></td><td>' + this.escapeHtml(data.job_name) + '</td></tr>';
      html += '<tr><td><strong>API Call:</strong></td><td><code>' + this.escapeHtml(data.api_call) + '</code></td></tr>';
      html += '<tr><td><strong>Run Time:</strong></td><td>' + this.formatDate(data.run_time) + '</td></tr>';
      html += '<tr><td><strong>Command:</strong></td><td><code>' + this.escapeHtml(data.command || 'N/A') + '</code></td></tr>';
      html += '</table>';
      html += '</div>';

      if (data.formatted_data) {
        html += '<div class="detail-section">';
        html += '<h4>Execution Data</h4>';
        html += '<div class="formatted-data">';

        if (data.formatted_data.duration) {
          html += '<div class="data-item"><strong>Duration:</strong> ' + data.formatted_data.duration + '</div>';
        }

        if (data.formatted_data.memory_usage) {
          html += '<div class="data-item"><strong>Memory Usage:</strong> ' + data.formatted_data.memory_usage + '</div>';
        }

        if (data.formatted_data.records_processed) {
          html += '<div class="data-item"><strong>Records Processed:</strong> ' + data.formatted_data.records_processed + '</div>';
        }

        if (data.formatted_data.status) {
          html += '<div class="data-item"><strong>Status:</strong> ' + this.getStatusHtml(data.formatted_data.status) + '</div>';
        }

        html += '</div>';
        html += '</div>';
      }

      if (data.data) {
        html += '<div class="detail-section">';
        html += '<h4>Raw Data</h4>';
        html += '<pre class="raw-data">' + this.escapeHtml(data.data) + '</pre>';
        html += '</div>';
      }

      html += '</div>';
      return html;
    },

    // Show modal
    showModal: function(title, content) {
      var modalHtml = `
        <div id="job-stats-modal" class="modal-overlay">
          <div class="modal-container">
            <div class="modal-header">
              <h3>${this.escapeHtml(title)}</h3>
              <button type="button" class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
              <div id="modal-content">${content}</div>
            </div>
          </div>
        </div>
      `;

      $('body').append(modalHtml);
      $('#job-stats-modal').fadeIn(300);
    },

    // Update modal content
    updateModalContent: function(content) {
      $('#modal-content').html(content);
    },

    // Close modal
    closeModal: function() {
      $('#job-stats-modal').fadeOut(300, function() {
        $(this).remove();
      });
    },

    // Show settings modal
    showSettingsModal: function() {
      var content = `
        <div class="settings-form">
          <div class="form-group">
            <label>
              <input type="checkbox" id="auto-refresh-setting" ${this.config.autoRefresh ? 'checked' : ''}>
              Enable auto-refresh
            </label>
          </div>
          <div class="form-group">
            <label for="refresh-interval">Refresh interval (seconds):</label>
            <input type="number" id="refresh-interval" value="${this.config.refreshInterval / 1000}" min="30" max="3600">
          </div>
          <div class="form-group">
            <label for="default-chart">Default chart type:</label>
            <select id="default-chart">
              <option value="executions" ${this.config.defaultChartType === 'executions' ? 'selected' : ''}>Executions</option>
              <option value="duration" ${this.config.defaultChartType === 'duration' ? 'selected' : ''}>Duration</option>
              <option value="errors" ${this.config.defaultChartType === 'errors' ? 'selected' : ''}>Errors</option>
              <option value="success" ${this.config.defaultChartType === 'success' ? 'selected' : ''}>Success Rate</option>
            </select>
          </div>
          <div class="form-actions">
            <button type="button" class="crm-button" onclick="JobStatsManager.saveSettingsFromModal()">Save Settings</button>
            <button type="button" class="crm-button crm-button-secondary" onclick="JobStatsManager.closeModal()">Cancel</button>
          </div>
        </div>
      `;

      this.showModal('Settings', content);
    },

    // Save settings from modal
    saveSettingsFromModal: function() {
      this.config.autoRefresh = $('#auto-refresh-setting').is(':checked');
      this.config.refreshInterval = parseInt($('#refresh-interval').val()) * 1000;
      this.config.defaultChartType = $('#default-chart').val();

      this.saveSettings();
      this.setupAutoRefresh();
      this.closeModal();

      this.showNotification('Settings saved successfully', 'success');
    },

    // Setup auto-refresh
    setupAutoRefresh: function() {
      if (this.state.refreshTimer) {
        clearInterval(this.state.refreshTimer);
        this.state.refreshTimer = null;
      }

      if (this.config.autoRefresh) {
        this.state.refreshTimer = setInterval(() => {
          this.loadData();
        }, this.config.refreshInterval);
      }
    },

    // Export data
    exportData: function() {
      var data = {
        timestamp: new Date().toISOString(),
        filters: this.state.filters,
        // In a real implementation, you would gather all current data
        message: 'Export functionality would be implemented here'
      };

      var blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = 'job-stats-' + new Date().toISOString().split('T')[0] + '.json';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },

    // Show loading state
    showLoadingState: function() {
      $('.loading-indicator').show();
      $('#refresh-stats').prop('disabled', true).html('<i class="crm-i fa-spinner fa-spin" role="img" aria-hidden="true"></i> Loading...');
    },

    // Hide loading state
    hideLoadingState: function() {
      $('.loading-indicator').hide();
      $('#refresh-stats').prop('disabled', false).html('<i class="crm-i fa-sync" role="img" aria-hidden="true"></i> Refresh');
    },

    // Show notification
    showNotification: function(message, type) {
      type = type || 'info';

      if (typeof CRM !== 'undefined' && CRM.alert) {
        CRM.alert(message, 'Job Statistics', type);
      } else {
        // Fallback notification
        var notification = $('<div class="notification notification-' + type + '">' + message + '</div>');
        $('body').append(notification);
        notification.fadeIn(300).delay(3000).fadeOut(300, function() {
          $(this).remove();
        });
      }
    },

    // Show error
    showError: function(message) {
      this.showNotification(message, 'error');
      console.error('Job Stats Error:', message);
    },

    // Utility functions
    formatNumber: function(num) {
      return new Intl.NumberFormat().format(num);
    },

    formatDate: function(dateString) {
      return new Date(dateString).toLocaleString();
    },

    escapeHtml: function(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },

    // Cleanup
    destroy: function() {
      if (this.state.refreshTimer) {
        clearInterval(this.state.refreshTimer);
      }

      if (this.state.chart) {
        this.state.chart.destroy();
      }

      $(document).off('.jobstats');
    }
  };

  // Initialize when document is ready
  $(document).ready(function() {
    // Only initialize if we're on the job stats page
    if ($('#performance-chart').length > 0) {
      JobStatsManager.init();

      // Make available globally for debugging
      window.JobStatsManager = JobStatsManager;
    }
  });

  // Handle page unload
  $(window).on('beforeunload', function() {
    if (window.JobStatsManager) {
      window.JobStatsManager.destroy();
    }
  });

})(CRM.$);
