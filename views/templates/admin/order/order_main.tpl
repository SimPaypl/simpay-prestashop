<div class="card mt-2" id="simpay_order_payments_block">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 class="card-header-title mb-0">
      {l s='SimPay Payments' d='Modules.Simpay.Admin'} ({$simpay_attempts_count|intval})
    </h3>
    <img
      src="{$simpay_module_dir|escape:'html':'UTF-8'}views/img/option/simpay.svg"
      alt="SimPay"
      style="height:20px; display:block;"
    >
  </div>

  <div class="card-body">
    <div class="mt-2">
      <ul class="nav nav-tabs d-print-none" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="simpayPaymentsTab" data-toggle="tab" href="#simpayPaymentsTabContent" role="tab" aria-controls="simpayPaymentsTabContent" aria-selected="true">
            <i class="material-icons">payments</i>
            {l s='Payments' d='Modules.Simpay.Admin'} ({$simpay_attempts_count|intval})
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="simpayLogsTab" data-toggle="tab" href="#simpayLogsTabContent" role="tab" aria-controls="simpayLogsTabContent" aria-selected="false">
            <i class="material-icons">notes</i>
            {l s='Logs' d='Modules.Simpay.Admin'} (<span class="count">{$simpay_logs_count|intval}</span>)
          </a>
        </li>
      </ul>

      <div class="tab-content border-0 shadow-none mb-0">
        <div class="tab-pane d-print-block fade show active" id="simpayPaymentsTabContent" role="tabpanel" aria-labelledby="simpayPaymentsTab">
          <div class="card-details">
            {if $simpay_attempts|@count}
              <table class="table" data-role="simpay-payments-grid-table">
                <thead>
                  <tr>
                    <th class="table-head-date">{l s='Date' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-payment">{l s='Method' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-transaction">{l s='Transaction ID' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-amount">{l s='Status' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-invoice">{l s='Active' d='Modules.Simpay.Admin'}</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $simpay_attempts as $attempt}
                    <tr>
                      <td data-role="date-column">{$attempt.created_at|escape:'html':'UTF-8'}</td>
                      <td data-role="payment-method-column">
                        {l s='SimPay' d='Modules.Simpay.Admin'}{if $attempt.channel} – {$attempt.channel|escape:'html':'UTF-8'}{/if}
                      </td>
                      <td data-role="transaction-id-column">{$attempt.transaction_id|escape:'html':'UTF-8'}</td>
                      <td data-role="status-column">{$attempt.status|default:'-'|escape:'html':'UTF-8'}</td>
                      <td data-role="active-column" class="text-center align-middle">
                        {if $attempt.is_active}
                          <span class="badge badge-success">{l s='Yes' d='Modules.Simpay.Admin'}</span>
                        {else}
                          <span class="badge badge-danger">{l s='No' d='Modules.Simpay.Admin'}</span>
                        {/if}
                      </td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            {else}
              <p class="text-muted">{l s='No payment attempts.' d='Modules.Simpay.Admin'}</p>
            {/if}
          </div>
        </div>

        <div class="tab-pane d-print-block fade" id="simpayLogsTabContent" role="tabpanel" aria-labelledby="simpayLogsTab">
          <div class="card-details">
            {if $simpay_logs|@count}
              <table class="table" data-role="simpay-logs-grid-table">
                <thead>
                  <tr>
                    <th>{l s='Date' d='Modules.Simpay.Admin'}</th>
                    <th>{l s='Level' d='Modules.Simpay.Admin'}</th>
                    <th>{l s='Message' d='Modules.Simpay.Admin'}</th>
                    <th>{l s='Context' d='Modules.Simpay.Admin'}</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach $simpay_logs as $log}
                    <tr>
                      <td>{$log.created_at|escape:'html':'UTF-8'}</td>
                      <td>{$log.level|escape:'html':'UTF-8'}</td>
                      <td>{$log.message|escape:'html':'UTF-8'}</td>
                      <td><small>{$log.context|default:'{}'|escape:'html':'UTF-8'}</small></td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            {else}
              <p class="text-muted">{l s='No logs for this order.' d='Modules.Simpay.Admin'}</p>
            {/if}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>