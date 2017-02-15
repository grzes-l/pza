<?

// todo back jakoś nieładnie działa, chyba mogłoby lepiej!!
    class insider_rights extends insider_table
    {
        public $fields = array(
            "name" =>    array("Nazwa uprawnienia", "regexp" => ".+"),
            "short" =>   array("Identyfikator", "regexp" => "[a-z]+(:[a-z0-9_()]+)+"),
            "access" =>   "Prawa dostępu",
            "active" =>  array("Aktywne?", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie")),
            "public" =>  array("Publiczne?", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie")),
            "price" => array("Cena", "regexp" => "[0-9]+([,.][0-9]*)?", "empty" => true)
        );

        /*
         * ALTER TABLE `pza`.`rights`
ADD COLUMN `price` INT(11) ZEROFILL NULL COMMENT '' AFTER `public`;

         */

        protected $capt = "<name>";
        protected $order = "name";

        function __construct($profile = false)
        {
            if(!access::has("god"))
                unset($this->fields["access"]);

            parent::__construct($profile);
        }
    }
