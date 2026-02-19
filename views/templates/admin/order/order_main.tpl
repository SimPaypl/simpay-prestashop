<div class="card mt-2" id="simpay_order_payments_block">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 class="card-header-title mb-0">
      {l s='SimPay Payments' d='Modules.Simpay.Admin'} ({$simpay_attempts|count})
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
          <a class="nav-link {if !$simpay_refund_status}active{/if}" id="simpayPaymentsTab" data-toggle="tab" href="#simpayPaymentsTabContent" role="tab" aria-controls="simpayPaymentsTabContent" aria-selected="{if !$simpay_refund_status}true{else}false{/if}">
            <i class="material-icons">payments</i>
            {l s='Payments' d='Modules.Simpay.Admin'} ({$simpay_attempts|count})
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link {if $simpay_refund_status}active{/if}" id="simpayRefundsTab" data-toggle="tab" href="#simpayRefundsTabContent" role="tab" aria-controls="simpayRefundsTabContent" aria-selected="{if $simpay_refund_status}true{else}false{/if}">
            <i class="material-icons">rotate_left</i>
            {l s='Refunds' d='Modules.Simpay.Admin'} ({$simpay_refunds|count})
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="simpayLogsTab" data-toggle="tab" href="#simpayLogsTabContent" role="tab" aria-controls="simpayLogsTabContent" aria-selected="false">
            <i class="material-icons">notes</i>
            {l s='Logs' d='Modules.Simpay.Admin'} ({$simpay_logs|count})
          </a>
        </li>
      </ul>

      <div class="tab-content border-0 shadow-none mb-0">
        <div class="tab-pane d-print-block fade {if !$simpay_refund_status}show active{/if}" id="simpayPaymentsTabContent" role="tabpanel" aria-labelledby="simpayPaymentsTab">
          <div class="card-details">
            {if $simpay_attempts|@count}
              <table class="table" data-role="simpay-payments-grid-table">
                <thead>
                  <tr>
                    <th class="table-head-date">{l s='Date' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-method">{l s='Method' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-transaction">{l s='Transaction ID' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-status text-center align-middle">{l s='Status' d='Modules.Simpay.Admin'}</th>
                    <th class="table-head-active text-center align-middle">{l s='Active' d='Modules.Simpay.Admin'}</th>
                  </tr>
                </thead>
                <tbody>

                {* Status labels *}
                {assign var="status_map" value=[
                'transaction_new'        => {l s='New' d='Modules.Simpay.Admin'},
                'transaction_paid'       => {l s='Paid' d='Modules.Simpay.Admin'},
                'transaction_confirmed'  => {l s='Confirmed' d='Modules.Simpay.Admin'},
                'transaction_canceled'   => {l s='Canceled' d='Modules.Simpay.Admin'},
                'transaction_fraud'      => {l s='Fraud suspected' d='Modules.Simpay.Admin'},
                'transaction_failure'    => {l s='Failed' d='Modules.Simpay.Admin'},
                'transaction_expired'    => {l s='Expired' d='Modules.Simpay.Admin'},
                'transaction_refunded'   => {l s='Refunded' d='Modules.Simpay.Admin'}
                ]}

                {* Status badge colors *}
                {assign var="badge_map" value=[
                'transaction_new'        => 'badge-warning',
                'transaction_paid'       => 'badge-success',
                'transaction_confirmed'  => 'badge-success',
                'transaction_canceled'   => 'badge-secondary',
                'transaction_fraud'      => 'badge-danger',
                'transaction_failure'    => 'badge-danger',
                'transaction_expired'    => 'badge-warning',
                'transaction_refunded'   => 'badge-info'
                ]}

                {foreach $simpay_attempts as $attempt}
                    <tr>
                      <td data-role="date-column">{$attempt.created_at|escape:'html':'UTF-8'}</td>
                      <td data-role="payment-method-column">
                        {l s='SimPay' d='Modules.Simpay.Admin'}{if $attempt.channel} – {$attempt.channel|escape:'html':'UTF-8'}{/if}
                      </td>
                      <td data-role="transaction-id-column">{$attempt.transaction_id|escape:'html':'UTF-8'}</td>
                      <td data-role="status-column" class="text-center align-middle">
                        <span class="badge {$badge_map[$attempt.status]|default:'badge-info'}">
                            {$status_map[$attempt.status]|default:{l s='Unknown' d='Modules.Simpay.Admin'}}
                        </span>
                      </td>
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

        <div class="tab-pane d-print-block fade {if $simpay_refund_status}show active{/if}" id="simpayRefundsTabContent" role="tabpanel" aria-labelledby="simpayPaymentsTab">
          <div class="card-details">

            {if $simpay_refund_status}
              <div class="simpay-alert mb-2">{$simpay_refund_status nofilter}</div>
            {/if}

            {if $simpay_is_refund_possible}
              <form method="post" action="{$simpay_refund_action|escape:'html':'UTF-8'}" data-simpay-refund-form>
                <div class="mb-3">
                  <div class="form-row align-items-end">
                    <div class="col-md-2">
                      <label class="form-control-label mb-1">{l s='Refund type' d='Modules.Simpay.Admin'}</label>
                      <select class="custom-select pt-2 h-100" name="simpay_refund_type" data-simpay-refund-type>
                        <option value="full">{l s='Full refund' d='Modules.Simpay.Admin'}</option>
                        <option value="partial">{l s='Partial refund' d='Modules.Simpay.Admin'}</option>
                      </select>
                    </div>
                    <div class="col-md-2">
                      <label class="form-control-label mb-1">{l s='Amount' d='Modules.Simpay.Admin'}</label>
                      <div class="input-group">
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          max="{$simpay_refund_max_amount|escape:'html':'UTF-8'}"
                          class="form-control"
                          name="simpay_refund_amount"
                          data-simpay-refund-amount
                        >
                        <div class="input-group-append">
                          <span class="input-group-text">{$simpay_currency_sign|escape:'html':'UTF-8'}</span>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <button type="submit" name="simpay_refund_submit" value="1" class="btn btn-primary">
                        {l s='Create refund' d='Modules.Simpay.Admin'}
                      </button>
                    </div>
                  </div>
                  <small class="text-muted d-block mt-1">
                    {l s='Select full or partial refund. Partial refunds require an amount.' d='Modules.Simpay.Admin'}
                  </small>
                </div>
              </form>
            {/if}

            {if $simpay_refunds|@count}
              <table class="table" data-role="simpay-refunds-grid-table">
                <thead>
                <tr>
                  <th class="table-head-date">{l s='Refund date' d='Modules.Simpay.Admin'}</th>
                  <th class="table-head-type">{l s='Refund type' d='Modules.Simpay.Admin'}</th>
                  <th class="table-head-transaction">{l s='Transaction ID' d='Modules.Simpay.Admin'}</th>
                  <th class="table-head-transaction">{l s='Refund ID' d='Modules.Simpay.Admin'}</th>
                  <th class="table-head-amount">{l s='Amount' d='Modules.Simpay.Admin'}</th>
                  <th class="table-head-status text-center align-middle">{l s='Status' d='Modules.Simpay.Admin'}</th>
                </tr>
                </thead>
                <tbody>

                {* Status labels map *}
                {assign var="status_map" value=[
                'refund_new'       => {l s='New' d='Modules.Simpay.Admin'},
                'refund_pending'   => {l s='Processing' d='Modules.Simpay.Admin'},
                'refund_completed' => {l s='Completed' d='Modules.Simpay.Admin'},
                'refund_rejected'  => {l s='Rejected' d='Modules.Simpay.Admin'},
                'refund_failed'    => {l s='Failed' d='Modules.Simpay.Admin'}
                ]}

                {foreach $simpay_refunds as $refund}
                  <tr>
                    <td data-role="date-column">{$refund.created_at|escape:'html':'UTF-8'}</td>
                    <td data-role="type-column">
                      {if $refund.refund_type == 'full'}
                        {l s='Full refund' d='Modules.Simpay.Admin'}
                      {elseif $refund.refund_type == 'partial'}
                        {l s='Partial refund' d='Modules.Simpay.Admin'}
                      {else}
                        {l s='Unknown' d='Modules.Simpay.Admin'}
                      {/if}
                    </td>
                    <td data-role="transaction-id-column">{$refund.transaction_id|escape:'html':'UTF-8'}</td>
                    <td data-role="refund-id-column">{$refund.id_simpay_refund|escape:'html':'UTF-8'}</td>
                    <td data-role="amount-column">
                      {math equation="x/100" x=$refund.amount format="%.2f"} {$simpay_currency_sign|escape:'html':'UTF-8'}
                    </td>
                    <td data-role="status-column" class="text-center align-middle">
                      <span class="badge
                          {if $refund.status|in_array:['refund_new','refund_pending']}badge-warning
                          {elseif $refund.status == 'refund_completed'}badge-success
                          {elseif $refund.status|in_array:['refund_rejected','refund_failed']}badge-danger
                          {else}badge-info{/if}">

                          {$status_map[$refund.status]|default:{l s='Unknown' d='Modules.Simpay.Admin'}}

                      </span>
                    </td>
                  </tr>
                {/foreach}
                </tbody>
              </table>
            {else}
              <p class="text-muted">{l s='No refund attempts.' d='Modules.Simpay.Admin'}</p>
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