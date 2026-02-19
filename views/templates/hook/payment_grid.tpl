<div class="simpay-wrapper" style="width:100%;" data-payment-type="simpay">
    <form action="{$simpay_action|escape:'htmlall':'UTF-8'}"
          method="post"
          class="simpay-checkout-form"
          data-simpay-form>

        {* Hidden fields used by validate controller *}
        <input type="hidden" name="token" value="{$simpay_token|escape:'htmlall':'UTF-8'}">
        <input type="hidden" name="simpay" value="1">

        {if $simpay_show_methods && !empty($simpay_methods) }
        <ul class="simpay-payment-channels" data-simpay-grid="1">

            {foreach from=$simpay_methods item=m name=mm}
                {assign var=methodId value=$m.id|escape:'htmlall':'UTF-8'}
                {assign var=methodName value=$m.name|escape:'htmlall':'UTF-8'}

                <label for="simpay_{$methodId}" class="simpay-payment-channels__item">
                    <input
                            id="simpay_{$methodId}"
                            type="radio"
                            name="simpay_method_choice"
                            value="{$methodId}"
                            required="required"
                            style="display:none"
                    >

                    <div class="simpay-payment-channels__item-inner">
                        <span class="text-xs-center">{$methodName}</span>
                        {if !empty($m.img)}
                            <img class="img-fluid"
                                 src="{$m.img|escape:'htmlall':'UTF-8'}"
                                 alt="{$methodName}"
                                 width="80">
                        {/if}
                    </div>
                </label>
            {/foreach}

        </ul>
        {/if}
    </form>
    <div class="simpay-error" style="display:none">
        <span class="simpay-error__text">{l s='Please select a payment method' d='Modules.Simpay.Shop'}</span>
    </div>
</div>
