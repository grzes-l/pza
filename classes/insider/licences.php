<?

// todo back jakoś nieładnie działa, chyba mogłoby lepiej!!
    class insider_licences extends insider_entitlements
    {
        public function __construct($profile = false)
        {
            $this->table = 'entitlements';
            parent::__construct($profile);
        }

        function buy()
        {
            $query = "SELECT SQL_CALC_FOUND_ROWS " .
                " * " .
                " FROM rights AS r " .
                " WHERE r.price > 0 ";

            $this->S->assign("licences", vsql::retr($query));
            $this->S->assign("entitlements", insider_users::list_entitlements_groups(access::getuid()));

            $this->S->display("insider/licences_buy.html");
        }

        function proceed()
        {
            $id = null;
            $type = null;
            $trans = null;
            $licence = null;

            // na starcie sprawdzamy czy użytkownik nie posiada już rozpoczętej transakcji
            // jeśli tak to odmawiamy realizacji kolejnej
            $trans = vsql::get("select * from transaction where status>0");
            if ($trans) {
                $this->S->display("insider/licences_transation_active.html");
                return;
            }

            $user = access::getuid();
            $user_data = vsql::get("select * from users where id=" . $user);

            if ($_REQUEST['type']) {
                $type = $_REQUEST['type'];

                // szukamy licencji:
                $licence = vsql::get("select * from rights where id=" . vsql::quote($type) . " and price>0");
            }

            // nowa czy przedłużenie?
            if ($_REQUEST['id']) {
                $id = $_REQUEST['id'];

                // TODO: BUG!
                /*
                 * dostajemy ID z entitlements! później szukamy szczegółów licencji i jej transakcji!
                 */

                // szukamy transakcji
                $trans = vsql::get("select * from transaction where id=" . vsql::quote($id));

                if ($trans) {
                    $licence = vsql::get("select * from rigths where id=" . $trans['right']);
                }
            }

            if (!$trans) {
                // szukamy jakiejkolwiek poprzedniej transakcji użytkownika
                $trans = vsql::get("select * from transaction where user=" . $user);
            }

            foreach (array('id', 'type', 'trans', 'user_data', 'licence') as $item) {
//                echo "<br/><br/>$item<br/>"; print_r($$item);
                $this->S->assign($item, $$item);
            }

            // nowa szukamy danych w starych transakcjach
            // przedłużenie? bieżemy dane ze starej transakcji

            // wyświetlamy podsumowanie i krok dalej do płatności
            // wyświetlamy podsumowanie i możliwość zakupu
            // dodajemy rekord z transakcją

            if (isset($_POST['summary'])) {
                $this->S->display("insider/licences_summary.html");
                return;
            }
            if (isset($_POST['payment'])) {
                // przygotować dane dla payu
                // $payu_keys = vsql::$payu (array(key1=>'', key2=>''))

                // wpisujemy do pza.transaction nową transakcję

                $this->S->display("insider/licences_payment.html");
                return;
            }

            $this->S->display("insider/licences_proceed.html");
        }


        function ok()
        {
            // komunikat o tym że płatność została poprawnie przyjęta do rozliczenia
        }

        function err()
        {
            // komunikat o błędzie płatności
        }

        function payu()
        {
            // przyjmowanie statusów z payU
            $fields = array('pos_id', 'session_id', 'ts', 'sig');
            $trans = null;
            $sig = null;

            foreach ($fields as $field) {
                if (!isset($_POST[$field])) {
                    exit("Missing field: $field");
                }

                $fields[$field] = $_POST['field'];

                if ($field != 'sig')
                    $sig .= $_POST['field'];
            }

            $sig .= vsql::$payu['key2'];
            $sig = md5($sig);

            if ($_POST['pos_id'] != vsql::$payu['pos_id']) {
                exit("Unknown POS_ID: " . $_POST['pos_id']);
            }

            if ($sig != $_POST['sig']) {
                exit("Wrong SIG!");
            }

            // szukamy transakcji:
            $trans = vsql::get("selct * from transaction " .
                " where session_id=" . vsql::quote($_POST['session_id'])
            );

            if (!$trans)
                exit("Unknown transaction, session_id: " . $_POST['session_id']);


            $status = $this->get_transaction_status($fields);

            // update transaction status
            $s = $status['trans_status'];
            vsql::query("update tranasction set status=" . vsql::quote($s) .
                " where session_id=" . vsql::quote($fields['session_id'])
                );

            exit("OK");
        }

        protected function get_transaction_status($fields)
        {
            // jest ok? pobieramy pełny status:
            $url = "https://UrlSecure.payu.com.pl/UTF-8/Payment/get/txt";

            // use key 'http' even if you send the request to https://...
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($fields)
                )
            );
            $context  = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            if ($result === FALSE) { /* Handle error */ }

            return $this->extract_result($result);
        }

        protected function extract_result($data)
        {
            $result = array();

            foreach (explode("\n", $data) as $line) {
                list($key, $val) = explode(":", $line);
                $result[$key] = $val;
            }

            $sig = null;
            foreach (array('pos_id', 'session_id', 'order_id', 'status', 'amount', 'desc', 'ts') as $item) {
                $key = 'trans_' . $item;
                if (!isset($result[$key]))
                    exit('Missing key: ' . $key . ' in Payment/get procedure response');

                $sig .=  $result[$key];
            }
            $sig .= vsql::$payu['key2'];
            $sig = md5($sig);

            if (!isset($result['trans_sig']))
                exit("Missing sig in Payment/get procedure response");

            if ($sig != $result['trans_sig'])
                exit("Wring sig for Payment/get procedure response");

            return $result;
        }
    }
