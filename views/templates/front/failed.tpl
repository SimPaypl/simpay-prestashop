{extends "$layout"}

{block name="meta_title"}
  {l s='SimPay – payment issue' d='Modules.Simpay.Shop'}
{/block}

{block name="meta_description"}
  {if $repayment_enabled}
    {l s='We could not finalize your payment. Please retry the payment from your email.' d='Modules.Simpay.Shop'}
  {else}
    {l s='We could not finalize your payment. Please contact the shop to complete your order.' d='Modules.Simpay.Shop'}
  {/if}
{/block}

{block name="content"}
  <section class="simpay-wrapper">
    <p class="alert alert-warning">
      {l s='We could not process your payment and the order is still waiting for a successful transaction.' d='Modules.Simpay.Shop'}
    </p>

    <h1 class="simpay-title">
      {l s='Payment issue' d='Modules.Simpay.Shop'}
    </h1>

    <div class="simpay-box">
      <h2 class="simpay-subtitle">
        {l s='What should you do now?' d='Modules.Simpay.Shop'}
      </h2>

      {if $repayment_enabled}
        <p class="simpay-text">
          {l s='Open the order confirmation email and click the “Retry payment” link to try again.' d='Modules.Simpay.Shop'}
        </p>

        <ul class="simpay-list">
          <li>{l s='Check your spam folder if you did not receive the email.' d='Modules.Simpay.Shop'}</li>
          <li>{l s='You can resend the confirmation from your order history.' d='Modules.Simpay.Shop'}</li>
        </ul>

        <p class="simpay-muted">
          {l s='Once the payment succeeds, the order status will update automatically.' d='Modules.Simpay.Shop'}
        </p>
      {else}
        <p class="simpay-text">
          {l s='Repayment is currently disabled. Please create a new order and complete the payment again.' d='Modules.Simpay.Shop'}
        </p>

        <ul class="simpay-list">
          <li>{l s='You can review your previous order in your order history.' d='Modules.Simpay.Shop'}</li>
        </ul>

        <p class="simpay-muted">
          {l s='If you need help, please contact the shop.' d='Modules.Simpay.Shop'}
        </p>
      {/if}

      <div class="simpay-actions">
        <a class="btn btn-primary" href="{$urls.pages.index}">
          {l s='Back to shop' d='Modules.Simpay.Shop'}
        </a>
      </div>
    </div>
  </section>
{/block}

{block name="link_rewrite"}
  simpay-payment-issue
{/block}
