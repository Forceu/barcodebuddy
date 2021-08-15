<?php


class TagManager {


    /**
     * Gets an array of locally stored tags
     *
     * @return Tag[]
     * @throws DbConnectionDuringEstablishException
     */
    public static function getStoredTags(): array {
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $res  = $db->query('SELECT * FROM Tags');
        $tags = array();
        while ($row = $res->fetchArray()) {
            array_push($tags, new Tag($row));
        }
        return $tags;
    }

    /**
     * Adds tag to DB
     *
     * @param $tagName
     * @param $itemid
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     *
     */
    public static function add(string $tagName, int $itemid): void {
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $db->exec("INSERT INTO Tags(tag, itemId) VALUES('$tagName', $itemid);");
    }

    /**
     * Returns true if $name is not saved as a tag yet
     *
     * @param string $name
     * @return bool
     * @throws DbConnectionDuringEstablishException
     */
    public static function tagNotInUse(string $name): bool {
        $db    = DatabaseConnection::getInstance()->getDatabaseReference();
        $count = $db->querySingle("SELECT COUNT(*) as count FROM Tags WHERE tag='" . $name . "' COLLATE NOCASE");
        return ($count == 0);
    }


    /**
     * Check if the given name includes any words that are associated with a product
     *
     * @param string $name
     * @param SQLite3 $db
     * @return int
     */
    public static function getProductIdByPossibleTag(string $name, SQLite3 $db): int {
        $res = $db->query(self::generateQueryFromName($name));
        if ($row = $res->fetchArray()) {
            return $row["itemId"];
        } else {
            return 0;
        }
    }


    /**
     * Delete tag from local db
     *
     * @param int $id
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     *
     */
    public static function delete(int $id): void {
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $db->exec("DELETE FROM Tags WHERE id='$id'");
    }


    /**
     * Generates the SQL for word search
     * @param string $name Product name
     * @return string
     */
    private static function generateQueryFromName(string $name): string {
        $words = cleanNameForTagLookup($name);
        $i     = 0;
        $query = "SELECT itemId FROM Tags ";
        while ($i < sizeof($words)) {
            if ($i == 0) {
                $query = $query . "WHERE tag LIKE '" . $words[$i] . "'";
            } else {
                $query = $query . " OR tag LIKE '" . $words[$i] . "'";
            }
            $i++;
        }
        return $query;
    }

}

class Tag {

    public $id;
    public $name;
    public $itemId;
    public $item;

    public function __construct(array $dbRow) {
        if (!$this->isValidRow($dbRow)) {
            throw new RuntimeException("Invalid row supplied to create Tag Object");
        }
        $this->id     = $dbRow['id'];
        $this->name   = $dbRow['tag'];
        $this->itemId = $dbRow['itemId'];
        $this->item   = "";
    }

    public function setName(string $name): void {
        $this->item = $name;
    }

    public function compare(Tag $otherTag): int {
        if ($this->item != "" && $otherTag->item != "")
            return strcmp(strtoupper($this->item), strtoupper($otherTag->item));
        if ($this->item == "" && $otherTag->item != "")
            return -1;
        if ($this->item != "" && $otherTag->item == "")
            return 1;
        return strcmp(strtoupper($this->name), strtoupper($otherTag->name));
    }

    private function isValidRow(array $dbRow): bool {
        return (array_key_exists('id', $dbRow) &&
            array_key_exists('tag', $dbRow) &&
            array_key_exists('itemId', $dbRow));
    }
}