{extends "$layout"}

{block name="content"}
    <section class="card card-block mb-2">
        <b class="mb-1">{l s='Trwa przekierowanie... Proszę czekać' mod='simpay'}</b>
        <p>{l s='Jeśli nie zostali Państwo przekierowani, prosimy o' mod='simpay'}</p>
        <form action="{$action}" method="get" class="mb-1" id="simpayRedirectForm">
            <button type="submit" class="btn btn-primary">
                {l s='naciśnięcie tutaj' mod='simpay'}
            </button>
        </form>
    </section>
    <script>
        window.onload = () => {
            document.getElementById('simpayRedirectForm').submit();
        };
    </script>
{/block}

{*{block name='javascript_bottom'}*}
{*    {include file="_partials/javascript.tpl" javascript=$javascript.bottom}*}
{*    <script type="text/javascript">*}
{*        setTimeout(function(){*}
{*            window.location.replace("{$action}");*}
{*        }, 5000);*}
{*    </script>*}
{*{/block}*}