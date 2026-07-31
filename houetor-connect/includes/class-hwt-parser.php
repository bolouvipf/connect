<?php
defined('ABSPATH') || exit;

class HWT_Parser {

    private $code;
    private $parts = array();

    public function __construct($code) {
        $this->code = sanitize_text_field($code);
        $this->parts = explode('-', $this->code, 3);
    }

    public function is_valid() {
        if (count($this->parts) !== 3) {
            return false;
        }
        if ($this->parts[0] !== 'HWT') {
            return false;
        }
        $profiles = array('ONG', 'BOUTIQUE', 'COACH', 'CM', 'MARKETING');
        if (!in_array(strtoupper($this->parts[1]), $profiles, true)) {
            return false;
        }
        return true;
    }

    public function get_profil() {
        if (!$this->is_valid()) {
            return '';
        }
        return strtoupper($this->parts[1]);
    }

    public function get_uuid() {
        if (!$this->is_valid()) {
            return '';
        }
        return $this->parts[2];
    }

    public function get_modules() {
        $profil = $this->get_profil();
        $modules = array();

        switch ($profil) {
            case 'ONG':
                $modules = array('annonces');
                break;
            case 'BOUTIQUE':
                $modules = array('produits', 'commandes');
                break;
            case 'COACH':
                $modules = array('formations');
                break;
            case 'CM':
                $modules = array('annonces', 'produits');
                break;
            case 'MARKETING':
                $modules = array('annonces', 'produits', 'formations');
                break;
        }

        return $modules;
    }

    public function to_array() {
        return array(
            'code'    => $this->code,
            'profil'  => $this->get_profil(),
            'uuid'    => $this->get_uuid(),
            'modules' => $this->get_modules(),
        );
    }
}
