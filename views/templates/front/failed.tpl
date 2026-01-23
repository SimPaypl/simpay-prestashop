{extends "$layout"}

{block name="meta_title"}
    {l s='SimPay – payment issue' d='Modules.Simpay.Shop'}
{/block}

{block name="meta_description"}
    {l s='There was an issue with your payment. You will receive a link to retry shortly.' d='Modules.Simpay.Shop'}
{/block}

{block name="meta_keywords"}
    {l s='payment, issue, retry' d='Modules.Simpay.Shop'}
{/block}

{block name="content"}
    <section id="main">
        <header class="page-header">
            <h1>{l s='Payment issue' d='Modules.Simpay.Shop'}</h1>
        </header>

        <section id="content" class="page-content">
            <p class="alert alert-warning">
                {l s='There was an issue while processing your payment. You will receive a link to retry the payment shortly.' d='Modules.Simpay.Shop'}
            </p>

            <p class="text-muted">
                {l s='If you do not receive the link within a few minutes, you can try to create order again.' d='Modules.Simpay.Shop'}
            </p>

            <p>
                <a class="btn btn-primary" href="{$urls.pages.index}">
                    {l s='Back to shop' d='Modules.Simpay.Shop'}
                </a>
            </p>
        </section>
    </section>
{/block}

{block name="link_rewrite"}
    simpay-payment-issue
{/block}
