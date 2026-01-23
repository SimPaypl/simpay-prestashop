{extends "$layout"}

{block name="content"}
    <section class="card card-block mb-2">
        <b class="mb-1">{l s='Redirecting… please wait' d='Modules.Simpay.Shop'}</b>
        <p>{l s='If you are not redirected automatically, please' d='Modules.Simpay.Shop'}</p>

        <form action="{$action}" method="get" class="mb-1" id="simpayRedirectForm">
            <button type="submit" class="btn btn-primary">
                {l s='click here' d='Modules.Simpay.Shop'}
            </button>
        </form>
    </section>

    <script>
        window.addEventListener('load', function () {
            document.getElementById('simpayRedirectForm').submit();
        });
    </script>
{/block}
