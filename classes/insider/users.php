<?
// todo a może "osiągnięcia" usera nie mają sensu...
// todo ujednolicić tabelki class='plain', czy jako osobne pola, czy <h2>...</h2>

    class insider_users extends insider_table
    {
        public $fields;

        static public $_fields = array(
            "surname" =>    array("Nazwisko", "regexp" => ".+", "pub" => "B"),
            "name" =>       array("Imię", "pub" => "B"),
            "login" =>      array("Login", "regexp" => "[a-z][a-z0-9_.]+", "suppress" => true, "empty" => true, "pub" => "*"),
            "birthdate" =>  array("Data urodzenia", "type" => "date", "suppress" => true, "empty" => true),
            "deathdate" =>  array("Data śmierci", "type" => "date", "suppress" => true, "empty" => true, "no" => "register,add"),
            "phone" =>      array("Numer telefonu", "suppress" => true, "pub" => "T"),
            "email" =>      array("Adres e-mail", "suppress" => true, "pub" => "M"),
            "sex"   =>      array("Płeć", "type" => "list", "options" => array("M", "K"), "suppress" => true, "pub" => "B"),
            "country" =>    array("Kraj", "type" => "list", "suppress" => true, "empty" => true),
            "district" =>   array("Województwo", "type" => "list", "suppress" => true, "empty" => true),
            "zip" =>        array("Kod pocztowy", "suppress" => true),
            "town" =>       array("Miasto", "consistency" => true, "suppress" => true),
            "street" =>     array("Ulica i adres", "suppress" => true),
            "pesel" =>      array("PESEL", "suppress" => true),
            "skype" =>      array("Skype", "suppress" => true, "pub" => "M"),
            "www" =>        array("Strona WWW", "suppress" => true, "pub" => "M"),
            "about" =>      array("O sobie", "type" => "html", "no" => "view,hist,register", "pub" => "*"),
            "access" =>     array("Prawa dostępu", "type" => "area", "no" => "register", "suppress" => true),
            "fav_categories" => array("Ulubione kategorie", "ref" => "categories", "by" => "path", "no" => "search", "multiple" => true, "empty" => true, "suppress" => true),
            "flags" =>      array("Profil", "type" => "flags", "no" => "register", "pub" => "B",
                    "options" => array(
                        "B" => "Publiczne imię, nazwisko, płeć, przynależność, data urodzenia",
                        "M" => "Publiczne e-mail, skype, WWW",
                        "T" => "Publiczny telefon",
                        "E" => "Publiczne uprawnienia PZA",
//                        "A" => "Domyślnie publiczne przejścia"
                    ))
        );

        public $columns = array("surname", "name", "org" => array("Aktualny klub", "order" => "org"));
        public $filters = array(
            "org" => array("Aktualny klub", "search" => "o.short"),
            "surname", "name", "town", "country", "district", "birthdate", "flags");
        protected $order = "surname, name";
        protected $capt = "<ref>";

        function __construct($profile = false)
        {
            $this->fields = static::$_fields;

            if(!access::has("god"))
                unset($this->fields["access"]);

            parent::__construct();
            $this->fields["country"]["options"] = placelist::get("countries");
            $this->fields["district"]["options"] = placelist::get("regions");
            if(access::has("edit(users)"))
                $this->actions["/insider/users/edit?about=1&"] = array("name" => "Edytuj 'o sobie'");
            if(access::glob("entmgr(*)"))
                $this->actions["/insider/users/addentl?&"] = array("name" => "Dodaj uprawnienie");
            $this->actions["/insider/users/achievements"] = array("name" => "Osiągnięcia", "target" => "_self");

            /* "passwd" as a separate priviledge does not make sense,
               since it is possible to hijack "god" */
            if(access::has("god"))
                $this->actions["/insider/passwd"] = array("name" => "Zmiana hasła", "target" => "_self");



            if($profile)
            {
                foreach(array("country", "district") as $f)
                {
                    $this->fields[$f]["type"] = "select";
                    $this->fields[$f]["options"] =
                        array_merge(array("" => "-- brak wyboru --"),
                                     array_combine($this->fields[$f]["options"], $this->fields[$f]["options"])
                        );
                }

                $this->fields["sex"]["type"] = "select";
                $this->fields["sex"]["options"] = array("" => "-- brak wyboru --", "K" => "Kobieta", "M" => "Mężczyzna");
            }
            else if(!isset($_REQUEST["about"]))
                unset($this->fields["about"]);
        }

        function validate($id, &$data)
        {
            $err = parent::validate($id, $data);

            if(isset($data["about"]))
                if(strlen($data["about"]) > 11000)
                    $err["data"] = "Maksymalna długość opisu przekroczona. Opis ma " . strlen($data["about"]) . " znaków, a może być max. 10 000";

            foreach(array("login" => "Ten login jest już zajęty", "pesel" => "Ten PESEL występuje już w bazie")
                        as $f => $msg)
                if($data[$f])
                    if(vsql::get("SELECT id FROM users WHERE deleted = 0 AND {$f} = " .
                        vsql::quote($data[$f]) . " AND id != " . vsql::quote($id)))
                        $err[$f] = $msg;

            if(!$id)
                if(vsql::get($qry = "SELECT id FROM users WHERE deleted = 0
                            AND surname = " . vsql::quote($data["surname"]) .
                          " AND name = " . vsql::quote($data["name"]) .
                          " AND birthdate = " . vsql::quote($data["birthdate"])))
                    $err["birthdate"] = "Użytkownik o takim imieniu, nazwisku i dacie urodzenia występuje już w bazie. Być może Twoje konto zostało przeniesione ze starej bazy PZA. <a href='/insider/checkin/recover'>Kliknij, aby spróbować procedury odzyskiwania hasła</a>.";
            return $err;
        }

        function update($id, $data)
        {
            $data["ref"] = trim($data["surname"] . " " . $data["name"]);
            $newid = parent::update($id, $data);
            if((!$id) && isset($_REQUEST["soc"]))
                if(is_numeric($_REQUEST["soc"]))
                {
                    /* Create new membership */
                    vsql::insert("memberships", array(
                        "member" => $_REQUEST["soc"],
                        "user" => $newid,
                        "starts" => "0000-00-00",
                        "due" => "9999-12-31",
                        "flags" => "R"
                    ));
                }

            return $newid;
        }

        public function achievements()
        {
            $id = $_REQUEST["id"];
            header("Location: /insider/achievements?user=" . $id);
        }

        static function list_memberships($id, $extra_sql = "")
        {
            $m = vsql::retr("SELECT m.starts, m.due, IF(m.due >= NOW() AND m.flags LIKE '%R%', 1, 0) AS status, o.short
                                FROM memberships AS m
                                JOIN members AS o ON o.id = m.member
                                WHERE m.user = " . vsql::quote($id) . $extra_sql .
                " AND m.deleted = 0 ORDER BY m.starts, m.due, o.short", "");
            return $m;
        }

        static function list_entitlements($id, $extra_sql = "")
        {
            $m = vsql::retr("SELECT e.starts, IF(e.due = '9999-12-31', '', e.due) AS due, IF(e.due >= NOW(), 1, 0) AS status, r.name, e.number, r.public
                            FROM entitlements AS e
                            JOIN rights AS r ON r.id = e.right
                            WHERE e.user = " . vsql::quote($id) . $extra_sql .
            " AND e.deleted = 0 ORDER BY e.starts, e.due, r.name", "");
            return $m;
        }

        function view()
        {
            $id = $_REQUEST["id"];
            if(access::has("view(users)"))
            {
                $this->S->assign(array(
                    "memberships" => $this->list_memberships($id),
                    "entitlements" => $this->list_entitlements($id)
                ));
            }
            else
                $this->S->assign("restricted", true);
            parent::view();
        }

        static function pub_conditions($pfx = "", $flags = "B")
        {
            if(!access::has("search(users)"))
                return
                " AND ({$pfx}id = " . vsql::quote(access::getuid()) .
                " OR {$pfx}flags LIKE '%{$flags}%') ";

            return "";
        }

        protected function retr_query($filters)
        {
            return "SELECT SQL_CALC_FOUND_ROWS t.id, t.surname, t.name, IFNULL(GROUP_CONCAT(DISTINCT o.short SEPARATOR ' | '), '') AS org " .
                " FROM " . $this->table . " AS t " .
                " LEFT JOIN memberships AS m ON m.due >= NOW() AND m.deleted = 0 AND m.user = t.id " .
                " LEFT JOIN members AS o ON o.id = m.member AND o.deleted = 0 " .
                " WHERE t.deleted = 0 " . $filters .
                $this->pub_conditions("t.") .
                " GROUP BY t.id ";
        }

        protected function access($perm)
        {
            if($perm == "search" || $perm == "view")
                return true;

            return parent::access($perm);
        }

        protected function fetch($id, $resolve = false, $nl = false, $extra = "")
        {
            $r = parent::fetch($id, $resolve, $nl, $extra . $this->pub_conditions());
            if((!access::has("view(users)")) && $id != access::getuid())
            {
                $flags = vsql::get("SELECT flags FROM users WHERE id = " . vsql::quote($id), "flags", "");
                foreach($this->fields as $f => $i)
                {
                    if(!isset($r[$f])) continue;

                    if(isset($i["pub"]))
                        if(strstr($flags, $i["pub"]) || $i["pub"] == "*")
                            continue;

                    unset($r[$f]);
                }
            }

            return $r;
        }

        function quicksearch()
        {
            $a = explode(" ", trim(preg_replace('/\s+/', ' ', $_REQUEST["q"])));
            if(!isset($a[1])) $a[1] = "";
            header("Location: /insider/users#surname=" . htmlspecialchars($a[0]) . "&name=" . htmlspecialchars($a[1]));
            exit;
        }

        function addentl()
        {
            header("Location: /insider/entitlements/add?user=" . $_REQUEST["id"]);
            exit;
        }

        function add()
        {
            $this->fields["soc"] =
                array("name" => "Aktualny klub", "type" => "select", "options" =>
                insider_checkin::member_list());
            parent::add();
        }


    }
