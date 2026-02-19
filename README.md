# SimPay – płatności online dla PrestaShop (8 / 9)

Moduł **SimPay** umożliwia realizację płatności online w sklepach opartych o **PrestaShop 8/9**.  
Integracja jest w pełni osadzona w ścieżce zakupowej, a dodatkowo zapewnia podgląd i obsługę płatności z poziomu panelu zamówienia w Back Office.

---

## Spis treści

- [Cechy](#cechy)
- [Wymagania](#wymagania)
- [Instalacja](#instalacja)
    - [Instalacja przez Back Office](#instalacja-przez-back-office)
    - [Instalacja przez FTP](#instalacja-przez-ftp)
- [Aktualizacja](#aktualizacja)
- [Konfiguracja](#konfiguracja)
    - [Dane uwierzytelniające](#dane-uwierzytelniające)
    - [Adresy komunikacji (webhook / notify)](#adresy-komunikacji-webhook--notify)
    - [Metody płatności i ich kolejność](#metody-płatności-i-ich-kolejność)
    - [Tryb testowy i produkcyjny](#tryb-testowy-i-produkcyjny)
    - [Ponowienie płatności (retry)](#ponowienie-płatności-retry)
- [Back Office – transakcje, zwroty i logi](#back-office--transakcje-i-logi)
    - [Płatności](#płatności)
    - [Zwroty](#zwroty)
    - [Logi](#logi)
- [Logi i diagnostyka](#logi-i-diagnostyka)
- [Znane problemy](#znane-problemy)
- [Wsparcie](#wsparcie)

---

## Cechy

Moduł dodaje do PrestaShop obsługę płatności SimPay oraz umożliwia m.in.:

- płatności osadzone w ścieżce zamówienia (checkout),
- prezentację dostępnych metod płatności w sklepie,
- możliwość **włączania i wyłączania metod płatności** oraz ustawiania ich kolejności,
- tryb **testowy** i **produkcyjny**,
- system logów i narzędzi diagnostycznych,
- **podgląd statusu wszystkich transakcji powiązanych z zamówieniem** w panelu administracyjnym (Back Office),
- trzy sposoby **ponawiania płatności**: w szczegółach zamówienia w panelu klienta, poprzez link w mailu potwierdzającym zamówienie oraz poprzez link w mailu **o wygaśnięciu płatności** (jeśli opcja jest włączona),
- mechanizm sprawdzania dostępności aktualizacji modułu (powiadomienie w Back Office + link do pobrania),
- **płatność BLIK 0** – klient pozostaje na stronie koszyka sklepu i wpisuje 6-cyfrowy kod BLIK wygenerowany w aplikacji mobilnej banku.
- obsługę multisklepu (multi-store) – konfiguracja modułu jest niezależna dla każdego sklepu, a ustawienia metod płatności są zapisywane osobno.
- możliwość wykonywania **zwrotów pełnych i częściowych** bezpośrednio z poziomu szczegółów zamówienia w Back Office.

---

## Wymagania

- Minimalna wersja PrestaShop: **8.0**
- PHP: zgodne z wymaganiami Twojej wersji PrestaShop
- Włączona możliwość instalacji modułów (BO)
- Dostęp do panelu SimPay w celu pobrania danych integracyjnych

> ⚠️ **Uwaga dotycząca bezpieczeństwa**  
> Moduł **SimPay nie wspiera wersji PrestaShop niższych niż 8.0**.
>
> Starsze wersje PrestaShop (1.6 / 1.7) zawierają liczne znane luki bezpieczeństwa,  
> niezałatane podatności oraz mechanizmy umożliwiające obejście zabezpieczeń  
> (m.in. backdoory, podatne endpointy i przestarzałe zależności).
>
> Z uwagi na charakter modułu (obsługa płatności i danych transakcyjnych),  
> **świadomie ograniczamy wsparcie wyłącznie do PrestaShop 8.x i nowszych**,  
> aby zapewnić odpowiedni poziom bezpieczeństwa, stabilności i zgodności z aktualnymi standardami.

---

## Instalacja

### Instalacja przez Back Office

1. Pobierz najnowszą paczkę modułu `simpay_prestashop_X.Y.Z.zip` z sekcji **Releases**.
2. Zaloguj się do panelu administracyjnego PrestaShop:  
   `http(s)://twoja-domena.pl/nazwa_katalogu_admina`
3. Przejdź do: **Moduły → Menedżer modułów** (lub **Moduły i usługi**).
4. Kliknij **Dodaj nowy moduł** i wskaż plik `.zip`.
5. Kliknij **Prześlij moduł** / **Załaduj moduł**.
6. Po instalacji kliknij **Konfiguruj**.

### Instalacja przez FTP

1. Pobierz paczkę `.zip` z sekcji **Releases**.
2. Rozpakuj archiwum.
3. Skopiuj katalog modułu do:  
   `modules/simpay/`
4. W panelu PrestaShop przejdź do **Menedżera modułów** i zainstaluj moduł.

---

## Aktualizacja

1. Zaktualizuj moduł tak samo jak w sekcji [Instalacja](#instalacja) (wgrywasz nową paczkę `.zip`).
2. W panelu przejdź do: **Parametry zaawansowane → Wydajność** i kliknij **Wyczyść pamięć podręczną**.
3. Jeśli moduł informuje o aktualizacji w BO – możesz przejść linkiem do pobrania najnowszej wersji.

> Moduł sprawdza dostępność aktualizacji z cache (np. 1h). Jeśli endpoint aktualizacji jest niedostępny, powiadomienie nie będzie wyświetlane.

---

## Konfiguracja

### Dane uwierzytelniające

W konfiguracji modułu uzupełnij dane otrzymane z panelu SimPay:

- **Hasło API (Bearer Token)**
- **ID usługi**
- **Klucz sygnatury IPN**

### Adresy komunikacji (webhook / notify)

W panelu SimPay dodaj adres do komunikacji IPN:

- `https://twoja-domena.pl/module/simpay/notify`

> Upewnij się, że sklep działa po HTTPS oraz że adres URL jest publicznie dostępny (bez basic auth).  
> Jeśli środowisko DEV jest zabezpieczone, użyj tunelu (np. Cloudflare Tunnel / ngrok) lub whitelisty IP, jeśli jest wspierana.


### Metody płatności i ich kolejność

Moduł umożliwia pełną kontrolę nad metodami płatności wyświetlanymi klientowi w procesie zakupowym.

W konfiguracji możesz:

- włączyć lub wyłączyć wyświetlanie metod płatności SimPay w sklepie,
- wybierać, które metody płatności mają być dostępne dla klientów,
- ustalać **kolejność metod płatności metodą „przeciągnij i upuść” (drag & drop)**,
- odświeżyć listę kanałów płatności pobieraną z panelu SimPay.

Konfiguracja odbywa się za pomocą dwóch list:
- **Wszystkie metody płatności** – lista metod dostępnych w SimPay,
- **Wybrane metody** – metody, które będą widoczne dla klientów w sklepie.

Jeśli dana metoda płatności nie jest dostępna na liście, należy dodać ją i aktywować w panelu SimPay w sekcji **Kanały płatności**.

Dodatkowo moduł pozwala na niezależne sterowanie wybranymi metodami:

- wyświetlanie **BLIK** jako dodatkowej metody płatności,
- wyświetlanie **BLIK** w formie widżetu,
- wyświetlanie **BLIK Płacę Później** jako osobnej metody,
- wyświetlanie **PayPo** jako osobnej metody płatności,
- włączanie lub wyłączanie **ponawiania płatności**.

---

### Tryb testowy i produkcyjny

Moduł obsługuje dwa tryby pracy:

- **Tryb testowy** – umożliwia sprawdzenie poprawności integracji bez realizowania rzeczywistych transakcji,
- **Tryb produkcyjny** – płatności są realizowane w środowisku produkcyjnym SimPay.

Zmiana trybu nie wymaga ponownej instalacji modułu.

---

### Ponowienie płatności (retry)

Jeżeli funkcja ponawiania płatności jest włączona, moduł umożliwia tworzenie wielu prób płatności dla jednego zamówienia.

Ponowienie płatności może zostać zainicjowane:

- z poziomu szczegółów zamówienia w panelu klienta,
- poprzez link zawarty w mailu potwierdzającym zamówienie,
- poprzez link w mailu o **wygaśnięciu płatności** (jeśli opcja jest włączona).

Każda próba płatności jest zapisywana jako osobna transakcja, a jej przebieg i status są widoczne zarówno w zakładce **Płatności**, jak i w **Logach**.

---


## Back Office – transakcje i logi

W widoku szczegółów zamówienia moduł dodaje sekcję **Płatności SimPay**, która umożliwia pełny wgląd w przebieg procesu płatności bez opuszczania panelu administracyjnego PrestaShop.

Sekcja została podzielona na dwie zakładki: **Płatności** oraz **Logi**.

---

### Płatności

Zakładka **Płatności** prezentuje wszystkie próby płatności powiązane z danym zamówieniem.

Dla każdej płatności wyświetlane są m.in.:

- data i godzina utworzenia transakcji,
- metoda płatności (np. BLIK),
- **ID transakcji w SimPay**,
- aktualny status transakcji (np. `transaction_expired`),
- informacja, czy dana próba płatności jest **aktywna**.

> **Uwaga:** w danym momencie **aktywna może być tylko jedna transakcja** – zawsze jest to **ostatnia (najbardziej aktualna) próba płatności**.  
> Poprzednie oraz opłacone już transakcje są automatycznie oznaczane jako nieaktywne.

Dzięki temu możliwe jest szybkie sprawdzenie:
- ile prób płatności zostało wykonanych,
- która transakcja jest aktualna,
- czy płatność została zakończona, wygasła lub nie powiodła się.

---

### Zwroty

Zakładka **Zwroty** wyświetla listę aktualnych zwrotów i umożliwia realizację zwrotów bezpośrednio z poziomu szczegółów zamówienia w Back Office.

Zwrot można utworzyć:

- jako **zwrot pełny** (całość opłaconej kwoty),
- jako **zwrot częściowy** (dowolna kwota nieprzekraczająca dostępnego salda do zwrotu).

Po utworzeniu zwrotu:

- operacja jest wysyłana do API SimPay,
- zwrot zostaje zapisany w systemie,
- jego status jest widoczny w tej samej zakładce w liście zwrotów.

Zwrot może zostać wykonany wyłącznie wtedy, gdy:

- istnieje **opłacona transakcja** powiązana z zamówieniem,
- suma wszystkich dotychczasowych zwrotów **nie przekracza wartości zamówienia**,
- transakcja znajduje się w stanie umożliwiającym wykonanie zwrotu po stronie SimPay.

Jeśli którykolwiek z warunków nie jest spełniony, moduł uniemożliwi utworzenie zwrotu.

### Logi

Zakładka **Logi** zawiera szczegółową historię zdarzeń związanych z obsługą płatności SimPay dla danego zamówienia.

Wyświetlane informacje obejmują m.in.:

- datę i godzinę zdarzenia,
- poziom logu (np. `info`, `resp`),
- opis zdarzenia (np. rozpoczęcie płatności, ponowienie płatności, zmiana statusu),
- kontekst techniczny zdarzenia (ID zamówienia, ID transakcji, statusy, typ zdarzenia).

Logi obejmują cały przebieg procesu, w tym:
- rozpoczęcie płatności,
- ponowienie płatności przez klienta,
- odebrane powiadomienia IPN / webhook,
- zmiany statusów transakcji,
- decyzje modułu (np. oznaczenie próby jako nieaktywnej),
- aktualizację statusu zamówienia w PrestaShop.

Zakładka ta jest szczególnie pomocna przy diagnostyce problemów oraz weryfikacji poprawności komunikacji z API SimPay.

---

## Znane problemy

- **Moduły One Page Checkout (OPC)** mogą modyfikować checkout i wpływać na wyświetlanie metod płatności.  
  W razie problemów przetestuj na domyślnym checkout PrestaShop lub skontaktuj się z dostawcą OPC.
- Jeśli sklep ma basic auth na środowisku testowym – webhooki mogą nie dochodzić.

---

## Wsparcie

Masz pytania lub chcesz zgłosić błąd?

- Utwórz zgłoszenie w zakładce **Issues** w tym repozytorium (zalecane).
- Dołącz:
    - wersję PrestaShop,
    - wersję PHP,
    - wersję modułu,
    - fragment logów (bez danych wrażliwych),
    - kroki odtworzenia problemu.
