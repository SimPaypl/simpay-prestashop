{extends "$layout"}

{block name="content"}
<section class="card card-block mb-2">
    <b class="mb-1">{l s='You will be redirected to SimPay payment gateway. Please wait...' mod='simpay'}</b>
    <p>{l s='If you are not redirected automatically' mod='simpay'}</p>
    <form action="{$action}" method="get" class="mb-1">
        <button type="submit" class="btn btn-primary">
          {l s='Click here' mod='simpay'}
        </button>
    </form>
  </section>
{/block}

{*{block name='javascript_bottom'}*}
{*    {include file="_partials/javascript.tpl" javascript=$javascript.bottom}*}
{*    <script type="text/javascript">*}
{*        setTimeout(function(){*}
{*            window.location.replace("{$action}");*}
{*        }, 5000);*}
{*    </script>*}
{*{/block}*}