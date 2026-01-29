{extends "$layout"}

{block name="meta_title"}
  {l s='SimPay – retry payment' d='Modules.Simpay.Shop'}
{/block}

{block name="meta_description"}
  {l s='Retry your SimPay payment if the previous attempt expired.' d='Modules.Simpay.Shop'}
{/block}

{block name="content"}
  <section class="card card-block mb-2">
    {if $canRetry}
      <h1 class="h4 mb-2">{l s='We are preparing your SimPay retry' d='Modules.Simpay.Shop'}</h1>
      <p class="mb-1">{l s='Order reference:' d='Modules.Simpay.Shop'} <strong>{$orderReference}</strong></p>
      <p>{l s='Click the button below to continue and finalize your payment.' d='Modules.Simpay.Shop'}</p>

      <form id="simpay-retry-form" action="{$retryValidateUrl}" method="post" class="mb-2">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="retry_order_id" value="{$retryOrderId|intval}">
        <input type="hidden" name="retry_token" value="{$retryToken|escape:'html':'UTF-8'}">
        <input type="hidden" name="retry_form_token" value="{$retryFormToken|escape:'html':'UTF-8'}">
        <button class="btn btn-primary" type="submit">
          {l s='Retry payment now' d='Modules.Simpay.Shop'}
        </button>
      </form>
    {else}
      <h1 class="h4 mb-2">{l s='This order has already been paid' d='Modules.Simpay.Shop'}</h1>
      <p class="mb-3">
        {l s='Order reference:' d='Modules.Simpay.Shop'} <strong>{$orderReference}</strong><br>
        {l s='The payment was already completed, no further action is needed.' d='Modules.Simpay.Shop'}
      </p>
      <a class="btn btn-secondary" href="{$orderViewUrl}">
        {l s='Go to order details' d='Modules.Simpay.Shop'}
      </a>
    {/if}
  </section>
{/block}
