{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}

{if $action eq 1 or $action eq 2 or $action eq 8 or $action eq 4}
  {include file="CRM/Admin/Form/Job.tpl"}
{else}
  {capture assign=docUrlText}{ts}(How to setup cron on the command line...){/ts}{/capture}
  {capture assign=runAllURL}{crmURL p='civicrm/admin/runjobs' q="reset=1"}{/capture}
  <div class="crm-scheduled-jobs-wrapper">
    <div class="jobs-header">
      <div class="jobs-header-content">
        <h1>{ts}Scheduled Jobs{/ts}</h1>
        <div class="jobs-header-actions">
          {if $action ne 1 and $action ne 2}
            <a href="{crmURL p='civicrm/admin/job/stats' q='reset=1'}" class="crm-modern-button crm-modern-button-secondary">
              <i class="crm-i fa-bar-chart" aria-hidden="true"></i>
              {ts}Job Statistics{/ts}
            </a>
            <a href="{crmURL p='civicrm/admin/joblog' q='reset=1'}" class="crm-modern-button crm-modern-button-secondary">
              <i class="crm-i fa-list-alt" aria-hidden="true"></i>
              {ts}View Logs{/ts}
            </a>
            <a href="{crmURL p='civicrm/admin/job/add' q='action=add&reset=1'}" class="crm-modern-button crm-modern-button-primary">
              <i class="crm-i fa-plus-circle" aria-hidden="true"></i>
              {ts}Add New Job{/ts}
            </a>
          {/if}
        </div>
      </div>
    </div>

    <div class="crm-modern-help">
      <p>{ts}CiviCRM relies on a number of scheduled jobs that run automatically on a regular basis. These jobs keep data up-to-date and perform other important tasks.{/ts}</p>
      <p>{ts 1=$runAllURL}For most sites, your system administrator should set up one or more 'cron' tasks to run the enabled jobs. You can also <a href="%1">run all scheduled jobs manually</a>, or run specific jobs from this screen.{/ts} {docURL page="sysadmin/setup/jobs" text=$docUrlText}</p>
    </div>

    {if $rows}
      {* Calculate status counts *}
      {assign var="activeCount" value=0}
      {assign var="inactiveCount" value=0}
      {assign var="totalCount" value=0}
      {foreach from=$rows item=row}
        {assign var="totalCount" value=$totalCount+1}
        {if $row.is_active eq 1}
          {assign var="activeCount" value=$activeCount+1}
        {else}
          {assign var="inactiveCount" value=$inactiveCount+1}
        {/if}
      {/foreach}

      <div class="jobs-filters-section">
        <div class="filters-grid">
          <div class="filter-group">
            <label>{ts}Status{/ts}</label>
            <select class="filter-select" id="statusFilter">
              {foreach from=$statusOptions key=value item=label}
                <option value="{$value}">{$label}</option>
              {/foreach}
            </select>
          </div>
          <div class="filter-group">
            <label>{ts}Frequency{/ts}</label>
            <select class="filter-select" id="frequencyFilter">
              {foreach from=$frequencyOptions key=value item=label}
                <option value="{$value}">{$label}</option>
              {/foreach}
            </select>
          </div>
          <div class="filter-group">
            <label>{ts}Last Run{/ts}</label>
            <select class="filter-select" id="lastRunFilter">
              <option value="all">{ts}Any Time{/ts}</option>
              <option value="hour">{ts}Last Hour{/ts}</option>
              <option value="day">{ts}Last Day{/ts}</option>
              <option value="week">{ts}Last Week{/ts}</option>
              <option value="never">{ts}Never Run{/ts}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="jobs-status-cards">
        <div class="status-card status-active">
          <h3>{ts}Active Jobs{/ts}</h3>
          <div class="status-number">{$statusCounts.active}</div>
          <div class="status-subtitle">
            {if $statusCounts.running > 0}
              {ts 1=$statusCounts.running}%1 currently running{/ts}
            {else}
              {ts}Next run scheduled{/ts}
            {/if}
          </div>
        </div>
        <div class="status-card status-inactive">
          <h3>{ts}Inactive Jobs{/ts}</h3>
          <div class="status-number">{$statusCounts.inactive}</div>
          <div class="status-subtitle">{ts}Disabled{/ts}</div>
        </div>
        <div class="status-card status-errors">
          <h3>{ts}Jobs with Errors{/ts}</h3>
          <div class="status-number">{$statusCounts.errors}</div>
          <div class="status-subtitle">{ts}Need attention{/ts}
            <div>
              <i class="crm-i fa-clock-o" aria-hidden="true"></i>
              <span class="">In Last 24 hours.</span>
            </div>
          </div>
        </div>
        <div class="status-card status-running">
          <h3>{ts}Currently Running{/ts}</h3>
          <div class="status-number">{$statusCounts.running}</div>
          <div class="status-subtitle">{ts}In progress{/ts}</div>
        </div>
        <div class="status-card status-running">
          <h3>{ts}Unsuccessful Job{/ts}</h3>
          <div class="status-number">{$statusCounts.unsuccessful}</div>
          <div class="status-subtitle">{ts}Not completed{/ts}</div>
        </div>
      </div>

      <div class="jobs-main-content">
        <div class="jobs-table-container">
          <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div>
              <span id="selectedCount">0</span> {ts}jobs selected{/ts}
            </div>
            <div style="display: flex; gap: 1rem;">
              <button class="crm-modern-button crm-modern-button-secondary" onclick="bulkAction('enable')">
                <i class="crm-i fa-play" aria-hidden="true"></i>
                {ts}Enable Selected{/ts}
              </button>
              <button class="crm-modern-button crm-modern-button-secondary" onclick="bulkAction('disable')">
                <i class="crm-i fa-pause" aria-hidden="true"></i>
                {ts}Disable Selected{/ts}
              </button>
              <button class="crm-modern-button crm-modern-button-secondary" onclick="bulkAction('run')">
                <i class="crm-i fa-play-circle" aria-hidden="true"></i>
                {ts}Run Now{/ts}
              </button>
            </div>
          </div>

          <div class="jobs-table-header">
            <h2>{ts}Job Management{/ts}</h2>
            <div class="jobs-table-actions">
              <div class="search-box">
                <svg class="search-icon icon" viewBox="0 0 24 24">
                  <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                </svg>
                <input type="text" placeholder="{ts escape='htmlattribute'}Search jobs...{/ts}" class="filter-input" id="searchInput">
              </div>
              <a href="{$runAllURL}" class="crm-modern-button crm-modern-button-secondary">
                <i class="crm-i fa-play" aria-hidden="true"></i>
                {ts}Run All Jobs{/ts}
              </a>
            </div>
          </div>

          <div id="ltype">
            {strip}
              {* handle enable/disable actions *}
              {include file="CRM/common/enableDisableApi.tpl"}
              <table class="jobs-table selector row-highlight">
                <thead>
                <tr class="columnheader">
                  <th class="checkbox-cell">
                    <input type="checkbox" class="job-checkbox" id="selectAll">
                  </th>
                  <th class="job-name-cell">{ts}Job Name & Description{/ts}</th>
                  <th>{ts}Status{/ts}</th>
                  <th>{ts}Frequency{/ts}</th>
                  <th>{ts}Last Run{/ts}</th>
                  <th>{ts}Next Run{/ts}</th>
                  <th>{ts}Success Rate{/ts}</th>
                  <th>{ts}Actions{/ts}</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$rows item=row}
                  <tr id="job-{$row.id}" class="crm-entity {cycle values="odd-row,even-row"} {$row.class}{if NOT $row.is_active} disabled{/if}" data-status="{$row.status_class}" data-status-execution="{$row.status_execution}" {if $row.last_run_timestamp}data-last-run-timestamp="{$row.last_run_timestamp}"{/if}>
                    <td class="checkbox-cell">
                      <input type="checkbox" class="job-checkbox job-select" value="{$row.id}">
                    </td>
                    <td class="crm-job-name job-name-cell">
                      <div class="job-name">
                        <span data-field="name">{$row.name}</span>
                      </div>
                      {* <div class="job-frequency">{$row.run_frequency}</div> *}
                      {if array_key_exists('description', $row) && $row.description}
                        <div class="job-description">{$row.description}</div>
                      {/if}
                      <div class="job-api-info">
                        <span class="job-api-entity">{ts}API Entity:{/ts} {$row.api_entity}</span> |
                        <span class="job-api-action">{ts}Action:{/ts} {$row.api_action}</span>
                      </div>
                    </td>
                    <td class="crm-job-status">
                      {if $row.is_active eq 1}
                        <span class="job-status-badge job-status-active">{ts}Active{/ts}</span>
                        {if $row.is_running}
                          <span class="job-status-badge job-status-running" style="margin-left: 0.5rem; animation: pulse 2s infinite;">
                              {ts}Running{/ts}
                            </span>
                        {/if}
                      {else}
                        <span class="job-status-badge job-status-inactive">{ts}Inactive{/ts}</span>
                      {/if}
                    </td>
                    <td class="crm-job-frequency">
                      {*
                      <div class="frequency-info">
                        <span>{$row.run_frequency}</span>
                      </div>
                      *}
                      <div class="job-frequency" data-job-frequency="{$row.run_frequency_label}">{$row.run_frequency}</div>
                    </td>
                    <td class="crm-job-last-run">
                      {if $row.last_run eq null}
                        <div class="job-last-run never">{ts}Never{/ts}</div>
                      {else}
                        <div class="job-last-run">{$row.last_run_relative}</div>
                        {if $row.has_errors}
                          <div class="log-preview" style="color: #ef4444; margin-top: 0.25rem;">
                            <i class="crm-i fa-exclamation-triangle" aria-hidden="true"></i>
                            {ts}Recent errors detected{/ts}
                          </div>
                        {else}
                          <div class="log-preview">
                            <i class="crm-i fa-check-circle" style="color: #10b981;" aria-hidden="true"></i>
                            {ts}Last run successful{/ts}
                          </div>
                          {if $row.last_run_duration}
                            <div class="job-run-duration">
                              <i class="crm-i fa-clock-o" aria-hidden="true"></i>
                              <span class="duration-text">Last run took {$row.last_run_duration}</span>
                            </div>
                          {/if}
                        {/if}
                      {/if}
                      {if $row.is_running}
                        <div class="progress-bar" style="margin-top: 0.5rem;">
                          <div class="progress-fill" style="width: 100%; animation: indeterminate 2s linear infinite;"></div>
                        </div>
                      {/if}
                    </td>
                    <td class="crm-job-next-run">
                      {if $row.next_run}
                        <div>{$row.next_run}</div>
                        <div class="next-run">{ts}Auto-scheduled{/ts}</div>
                      {else}
                        <div>-</div>
                        <div class="next-run">{ts}Disabled{/ts}</div>
                      {/if}
                    </td>
                    <td class="crm-job-success-rate">
                      {if $row.success_rate !== null}
                        <div class="tooltip" data-tooltip="{ts escape='htmlattribute' 1=$row.success_rate}%1% success rate over last 30 days{/ts}">
                            <span style="color: {if $row.success_rate >= 95}#10b981{elseif $row.success_rate >= 85}#f59e0b{else}#ef4444{/if}; font-weight: 600;">
                              {$row.success_rate}%
                            </span>
                        </div>
                      {else}
                        <span style="color: #6b7280;">-</span>
                      {/if}
                    </td>
                    <td class="job-actions">{$row.action|smarty:nodefaults|replace:'xx':$row.id}</td>
                  </tr>
                {/foreach}
                </tbody>
              </table>
            {/strip}
          </div>
        </div>

        {if $action ne 1 and $action ne 2}
          <div class="action-link">
            <a href="{crmURL p='civicrm/admin/job/add' q='action=add&reset=1'}" class="crm-modern-button crm-modern-button-primary">
              <i class="crm-i fa-plus-circle" aria-hidden="true"></i>
              {ts}Add New Scheduled Job{/ts}
            </a>
            <a href="{crmURL p='civicrm/admin/joblog' q='reset=1'}" class="crm-modern-button crm-modern-button-secondary">
              <i class="crm-i fa-list-alt" aria-hidden="true"></i>
              {ts}View Log (all jobs){/ts}
            </a>
          </div>
        {/if}
      </div>

    {elseif $action ne 1}
      <div class="no-jobs-message">
        <div class="no-jobs-icon">
          <i class="crm-i fa-clock-o" aria-hidden="true"></i>
        </div>
        <h3>{ts}No Scheduled Jobs Configured{/ts}</h3>
        <p>{ts}There are no jobs configured. Get started by adding your first scheduled job.{/ts}</p>
        <div style="margin-top: 2rem;">
          <a href="{crmURL p='civicrm/admin/job/add' q='action=add&reset=1'}" class="crm-modern-button crm-modern-button-primary">
            <i class="crm-i fa-plus-circle" aria-hidden="true"></i>
            {ts}Add New Scheduled Job{/ts}
          </a>
        </div>
      </div>
    {/if}
  </div>

  {* JavaScript for enhanced interactions *}
  <script>
    {literal}
    CRM.$(function($) {
      // Bulk selection functionality
      var selectAllCheckbox = $('#selectAll');
      var jobCheckboxes = $('.job-select');
      var bulkActions = $('#bulkActions');
      var selectedCount = $('#selectedCount');

      function updateBulkActions() {
        var checkedBoxes = $('.job-select:checked');
        var count = checkedBoxes.length;

        selectedCount.text(count);

        if (count > 0) {
          bulkActions.show();
        } else {
          bulkActions.hide();
        }
      }

      selectAllCheckbox.change(function() {
        jobCheckboxes.prop('checked', this.checked);
        updateBulkActions();
      });

      jobCheckboxes.change(function() {
        var allChecked = jobCheckboxes.length === $('.job-select:checked').length;
        var someChecked = $('.job-select:checked').length > 0;

        selectAllCheckbox.prop('checked', allChecked);
        selectAllCheckbox.prop('indeterminate', someChecked && !allChecked);

        updateBulkActions();
      });

      // Search functionality
      $('#searchInput').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();

        $('.jobs-table tbody tr').each(function() {
          var jobName = $(this).find('.job-name').text().toLowerCase();
          var jobDescription = $(this).find('.job-description').text().toLowerCase();
          var apiEntity = $(this).find('.job-api-entity').text().toLowerCase();
          var apiAction = $(this).find('.job-api-action').text().toLowerCase();

          if (jobName.indexOf(searchTerm) !== -1 ||
            jobDescription.indexOf(searchTerm) !== -1 ||
            apiEntity.indexOf(searchTerm) !== -1 ||
            apiAction.indexOf(searchTerm) !== -1) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      });

      // Filter functionality
      $('.filter-select').change(function() {
        var statusFilter = $('#statusFilter').val();
        var frequencyFilter = $('#frequencyFilter').val();
        var lastRunFilter = $('#lastRunFilter').val();

        $('.jobs-table tbody tr').each(function() {
          var show = true;

          // Status filter
          if (statusFilter !== 'all') {
            var rowStatus = $(this).data('status');
            if (statusFilter !== rowStatus) {
              show = false;
            }
            // Special handling for status jobs
            if ($(this).data('status-execution').split(' ').includes(statusFilter)) {
              show = true; // Show running jobs regardless of status
            }
          }

          // Frequency filter
          if (frequencyFilter !== 'all' && show) {
            //var frequency = $(this).find('.job-frequency').text().toLowerCase();
            var frequency = $(this).find('.job-frequency').data('job-frequency');
            if (frequency.indexOf(frequencyFilter) === -1) {
              show = false;
            }
          }
          // Last Run filter
          if (lastRunFilter !== 'all' && show) {
            var lastRunText = $(this).find('.job-last-run').text().toLowerCase();
            var lastRunTime = $(this).data('last-run-timestamp');
            var currentTime = Math.floor(Date.now() / 1000);

            switch (lastRunFilter) {
              case 'never':
                if (lastRunText.indexOf('never') === -1) {
                  show = false;
                }
                break;
              case 'hour':
                if (lastRunText.indexOf('never') !== -1 ||
                  (lastRunTime && (currentTime - lastRunTime) > 3600)) {
                  show = false;
                }
                break;
              case 'day':
                if (lastRunText.indexOf('never') !== -1 ||
                  (lastRunTime && (currentTime - lastRunTime) > 86400)) {
                  show = false;
                }
                break;
              case 'week':
                if (lastRunText.indexOf('never') !== -1 ||
                  (lastRunTime && (currentTime - lastRunTime) > 604800)) {
                  show = false;
                }
                break;
            }
          }

          if (show) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      });

      // Enhanced hover effects for job rows
      $('.jobs-table tbody tr').hover(
        function() {
          $(this).find('.job-actions a').addClass('visible');
        },
        function() {
          $(this).find('.job-actions a').removeClass('visible');
        }
      );

      // Add loading state to action buttons
      $('.jobs-table .crm-enable-disable a').click(function() {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('Processing...').addClass('disabled');

        // Reset after a delay (in real implementation, this would be handled by the AJAX callback)
        setTimeout(function() {
          $btn.text(originalText).removeClass('disabled');
        }, 2000);
      });

      // Refresh status periodically
      setInterval(function() {
        // This would make an AJAX call to refresh job statuses
        // updateJobStatuses();
      }, 30000); // Every 30 seconds
    });

    // Bulk action functions
    function bulkAction(action) {
      var selectedJobs = [];
      CRM.$('.job-select:checked').each(function() {
        selectedJobs.push(CRM.$(this).val());
      });

      if (selectedJobs.length === 0) {
        CRM.alert('Please select at least one job.', 'No Jobs Selected', 'warning');
        return;
      }

      var confirmMessage = '';
      switch (action) {
        case 'enable':
          confirmMessage = 'Are you sure you want to enable ' + selectedJobs.length + ' job(s)?';
          break;
        case 'disable':
          confirmMessage = 'Are you sure you want to disable ' + selectedJobs.length + ' job(s)?';
          break;
        case 'run':
          confirmMessage = 'Are you sure you want to run ' + selectedJobs.length + ' job(s) now?';
          break;
      }

      CRM.confirm({
        title: 'Confirm Bulk Action',
        message: confirmMessage
      }).on('crmConfirm:yes', function() {
        // Submit the bulk action
        var form = CRM.$('<form method="post">');
        form.append('<input type="hidden" name="bulk_action" value="' + action + '">');
        form.append('<input type="hidden" name="selected_jobs" value="' + selectedJobs.join(',') + '">');
        CRM.$('body').append(form);
        form.submit();
      });
    }

    {/literal}
  </script>

  {* Enhanced CSS Styles for Modern UI *}
  <style>
    .crm-scheduled-jobs-wrapper {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background: #f8fafc;
      margin: -20px;
      padding: 0;
    }

    .jobs-header {
      background: white;
      border-bottom: 1px solid #e2e8f0;
      padding: 1.5rem 2rem;
    }

    .jobs-header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .jobs-header h1 {
      font-size: 1.875rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .jobs-header-actions {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    .crm-modern-help {
      background: #f0f9ff;
      border: 1px solid #0ea5e9;
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin: 2rem;
      color: #0369a1;
    }

    .crm-modern-help p {
      margin: 0 0 0.75rem 0;
    }

    .crm-modern-help p:last-child {
      margin-bottom: 0;
    }

    .jobs-status-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin: 2rem;
      margin-bottom: 1rem;
    }

    .status-card {
      background: white;
      padding: 1.5rem;
      border-radius: 0.75rem;
      box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    }

    .status-card h3 {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6b7280;
      margin: 0 0 0.5rem 0;
    }

    .status-number {
      font-size: 2rem;
      font-weight: 700;
      margin: 0 0 0.25rem 0;
    }

    .jobs-filters-section {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      margin: 2rem;
      margin-bottom: 1rem;
      box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    }

    .filters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .filter-group label {
      font-weight: 500;
      font-size: 0.875rem;
      color: #374151;
    }

    .filter-input, .filter-select {
      padding: 0.625rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      transition: border-color 0.15s ease;
    }

    .filter-input:focus, .filter-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgb(59 130 246 / 0.1);
    }

    .search-box {
      position: relative;
    }

    .search-box input {
      padding: 0.5rem 0.75rem 0.5rem 2.5rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      width: 250px;
    }

    .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      width: 16px;
      height: 16px;
      fill: currentColor;
    }

    .bulk-actions {
      background: #3b82f6;
      color: white;
      padding: 1rem 1.5rem;
      align-items: center;
      justify-content: space-between;
    }

    .checkbox-cell {
      width: 40px;
    }

    .job-checkbox {
      width: 18px;
      height: 18px;
      accent-color: #3b82f6;
    }

    .job-status-running {
      background: #dbeafe;
      color: #1e40af;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }

    @keyframes indeterminate {
      0% { transform: translateX(-100%); }
      100% { transform: translateX(200%); }
    }

    .progress-bar {
      width: 100%;
      height: 6px;
      background: #e5e7eb;
      border-radius: 3px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: #3b82f6;
      border-radius: 3px;
      transition: width 0.3s ease;
    }

    .log-preview {
      font-size: 0.75rem;
      color: #64748b;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .frequency-info {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .next-run {
      font-size: 0.875rem;
      color: #6b7280;
    }

    .tooltip {
      position: relative;
    }

    .tooltip:hover::after {
      content: attr(data-tooltip);
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      background: #1f2937;
      color: white;
      padding: 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      white-space: nowrap;
      z-index: 10;
    }

    .status-active .status-number { color: #10b981; }
    .status-inactive .status-number { color: #6b7280; }
    .status-errors .status-number { color: #ef4444; }
    .status-running .status-number { color: #3b82f6; }

    .status-subtitle {
      font-size: 0.875rem;
      color: #6b7280;
    }

    .jobs-table-container {
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
      overflow: scroll;
    }

    .jobs-table-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e5e7eb;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .jobs-table-header h2 {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1f2937;
      margin: 0;
    }

    .jobs-table-actions {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    .crm-modern-button {
      padding: 0.625rem 1.25rem;
      border-radius: 0.5rem;
      font-weight: 500;
      text-decoration: none;
      border: none;
      cursor: pointer;
      font-size: 0.875rem;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .crm-modern-button-primary {
      background: #3b82f6;
      color: white;
    }

    .crm-modern-button-primary:hover {
      background: #2563eb;
      transform: translateY(-1px);
      color: white;
      text-decoration: none;
    }

    .crm-modern-button-secondary {
      background: white;
      color: #64748b;
      border: 1px solid #e2e8f0;
    }

    .crm-modern-button-secondary:hover {
      background: #f8fafc;
      border-color: #cbd5e1;
      color: #64748b;
      text-decoration: none;
    }

    .jobs-table {
      width: 100%;
      border-collapse: collapse;
    }

    .jobs-table th {
      background: #f9fafb;
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 600;
      font-size: 0.875rem;
      color: #374151;
      border-bottom: 1px solid #e5e7eb;
    }

    .jobs-table td {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: top;
    }

    .jobs-table tr:hover {
      background: #f8fafc;
    }

    .jobs-table tr.disabled {
      opacity: 0.6;
    }

    .job-name-cell {
      min-width: 300px;
    }

    .job-name {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 0.5rem;
      font-size: 1rem;
    }

    .job-frequency {
      font-size: 0.875rem;
      color: #3b82f6;
      background: #dbeafe;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      display: inline-block;
      margin-bottom: 0.5rem;
    }

    .job-description {
      font-size: 0.875rem;
      color: #6b7280;
      margin-bottom: 0.75rem;
      line-height: 1.4;
    }

    .job-api-info {
      font-size: 0.75rem;
      color: #9ca3af;
    }

    .job-api-entity {
      font-weight: 500;
    }

    .job-api-action {
      font-weight: 600;
      color: #374151;
    }

    .job-parameters {
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-size: 0.75rem;
      background: #f8fafc;
      padding: 0.75rem;
      border-radius: 0.375rem;
      color: #64748b;
      max-width: 200px;
      overflow-x: auto;
      white-space: pre-wrap;
    }

    .job-parameters.empty {
      font-style: italic;
      color: #9ca3af;
    }

    .job-last-run {
      font-size: 0.875rem;
      color: #374151;
    }

    .job-last-run.never {
      color: #9ca3af;
      font-style: italic;
    }

    .job-status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .job-status-active {
      background: #d1fae5;
      color: #065f46;
    }

    .job-status-inactive {
      background: #f3f4f6;
      color: #374151;
    }

    .job-actions {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }

    .job-actions a {
      padding: 0.375rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.15s ease;
    }

    .job-actions .crm-enable-disable a {
      background: #f3f4f6;
      color: #374151;
    }

    .job-actions .crm-enable-disable a:hover {
      background: #e5e7eb;
    }

    .action-link {
      margin: 2rem;
      display: flex;
      gap: 1rem;
      justify-content: center;
    }

    .no-jobs-message {
      background: white;
      border-radius: 0.75rem;
      padding: 3rem;
      text-align: center;
      margin: 2rem;
      box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    }

    .no-jobs-icon {
      font-size: 3rem;
      color: #9ca3af;
      margin-bottom: 1rem;
    }

    .crm-icon {
      width: 16px;
      height: 16px;
    }

    /* Override CiviCRM default styles */
    .crm-scheduled-jobs-wrapper .crm-content-block {
      background: transparent;
      border: none;
      margin: 0;
      padding: 0;
    }

    .crm-scheduled-jobs-wrapper .selector {
      border: none;
      box-shadow: none;
    }

    .crm-scheduled-jobs-wrapper #ltype {
      margin: 0;
    }
    .job-frequency-container {
      font-size: 0.875rem;
      line-height: 1.4;
    }

    .job-frequency-main {
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 0.5rem;
    }

    .job-constraints {
      margin-left: 0.5rem;
      border-left: 2px solid #e5e7eb;
      padding-left: 0.75rem;
    }

    .job-date-range,
    .job-time-window {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.25rem;
      font-size: 0.75rem;
      color: #6b7280;
    }

    .constraint-label {
      font-weight: 500;
      color: #374151;
      min-width: 80px;
    }

    .constraint-value {
      color: #6b7280;
    }

    .job-frequency-container .crm-i {
      width: 12px;
      height: 12px;
      color: #9ca3af;
    }
    .job-run-duration {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.75rem;
      color: #6b7280;
      padding: 0.125rem 0;
    }

    .job-run-duration .crm-i {
      width: 10px;
      height: 10px;
      color: #9ca3af;
    }
  </style>
{/if}
