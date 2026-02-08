<div class="simpay-wrapper simpay-blik-wrapper" data-payment-type="blik">
  {if $blik_type == 'widget'}
    <form
      action="{$simpay_blik_widget_action|escape:'htmlall':'UTF-8'}"
      method="post"
      class="simpay-blik-form"
      data-simpay-blik-form
      data-simpay-blik-action="{$simpay_blik_widget_action|escape:'htmlall':'UTF-8'}"
      data-simpay-msg-empty="{l s='Enter the BLIK code' d='Modules.Simpay.Shop'}"
      data-simpay-msg-invalid="{l s='Invalid BLIK code' d='Modules.Simpay.Shop'}"
      data-simpay-msg-failed="{l s='BLIK payment failed. Try again.' d='Modules.Simpay.Shop'}"
      data-simpay-msg-loading="{l s='Processing your data, please wait.' d='Modules.Simpay.Shop'}"
      data-simpay-msg-confirm="{l s='Confirm the payment in your banking app.' d='Modules.Simpay.Shop'}"
      data-simpay-msg-wait="{l s='Waiting for confirmation in your banking app…' d='Modules.Simpay.Shop'}"
      data-simpay-msg-rejected="{l s='Payment rejected. Please enter a new BLIK code.' d='Modules.Simpay.Shop'}"
      data-simpay-msg-expired="{l s='BLIK code expired. Please enter a new code.' d='Modules.Simpay.Shop'}"
      data-simpay-msg-success="{l s='Payment confirmed. Redirecting to order confirmation…' d='Modules.Simpay.Shop'}"
      data-simpay-msg-terms="{l s='Please accept the terms before continuing.' d='Modules.Simpay.Shop'}"
    >
      <input type="hidden" name="simpay" value="1">
      <input type="hidden" name="token" value="{$simpay_blik_widget_token|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="cart_id" value="{$simpay_blik_widget_cart_id|escape:'htmlall':'UTF-8'}">
      <input type="hidden" name="simpay_method_choice" value="blik">

      <div class="simpay-blik-widget">

        <div class="simpay-blik-widget__field">
          <input
            type="text"
            name="blik_code"
            inputmode="numeric"
            maxlength="7"
            minlength="6"
            class="simpay-blik-widget__input"
            data-simpay-blik-code
            aria-label="{l s='BLIK code' d='Modules.Simpay.Shop'}"
          >
          <label class="simpay-blik-widget__label" for="blik_code">Kod BLIK</label>
        </div>

        <div class="simpay-blik-widget__hint">
          <span class="simpay-blik-widget__loader" aria-hidden="true" data-simpay-blik-loader></span>
          <span data-simpay-blik-message>{l s='You can find the BLIK code in your banking app.' d='Modules.Simpay.Shop'}</span>
        </div>

        <div class="simpay-blik-widget__actions">
          <div data-simpay-terms-slot></div>
          <div data-simpay-main-button-slot></div>
        </div>

        <div class="simpay-blik-widget__logo">
          <img src="{$simpay_blik_widget_assets}views/img/option/simpay.svg"/>
        </div>
      </div>
    </form>
  {else}
    {l s='You’ll be redirected to the secure payment gateway.' d='Modules.Simpay.Shop'}
  {/if}
</div>
