<div id="simpay-repayment" class="box simpay-repayment">
    <div class="simpay-repayment__content">
        <h4 class="simpay-repayment__title">
            {l s='Repay with %s' sprintf=[$payment_name] d='Modules.Simpay.Shop'}
        </h4>

        <p class="simpay-repayment__text">
            {l s='Your payment is still being processed. If the payment was interrupted or cancelled, you can safely try again using the button below.' d='Modules.Simpay.Shop'}
        </p>

        <a
                href="{$simpay_retry_url|escape:'htmlall':'UTF-8'}"
                class="btn btn-primary simpay-repayment__button"
        >
            {l s='Repay now' d='Modules.Simpay.Shop'}
        </a>
    </div>

    <div class="simpay-repayment__logo">
        <img
                src="{$module_dir}views/img/option/simpay.svg"
                alt="{$payment_name|escape:'htmlall':'UTF-8'}"
        />
    </div>
</div>
