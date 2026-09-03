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
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $stmt = $db->prepare("INSERT INTO Tags(tag, itemId) VALUES(:tag, :itemId)");
        $stmt->bindValue(':tag', $tagName, SQLITE3_TEXT);
        $stmt->bindValue(':itemId', $itemid, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Returns true if $name is not saved as a tag yet
     *
     * @param string $name
     * @return bool
     * @throws DbConnectionDuringEstablishException
     */
    public static function tagNotInUse(string $name): bool {
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM Tags WHERE tag=:tag COLLATE NOCASE");
        $stmt->bindValue(':tag', $name, SQLITE3_TEXT);
        $count = $stmt->execute()->fetchArray()['count'];
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
        $words = cleanNameForTagLookup($name);
        if (empty($words)) {
            return 0;
        }
        $stmt = $db->prepare(self::generateQueryFromName($words));
        foreach ($words as $i => $word) {
            $stmt->bindValue(':word' . $i, $word, SQLITE3_TEXT);
        }
        $res = $stmt->execute();
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
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $stmt = $db->prepare("DELETE FROM Tags WHERE id=:id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }


    /**
     * Generates the parametrized SQL for word search
     * @param array $words Cleaned-up words from the product name
     * @return string SQL with named placeholders :word0, :word1, ...
     */
    private static function generateQueryFromName(array $words): string {
        $i     = 0;
        $query = "SELECT itemId FROM Tags ";
        while ($i < sizeof($words)) {
            if ($i == 0) {
                $query = $query . "WHERE tag LIKE :word" . $i;
            } else {
                $query = $query . " OR tag LIKE :word" . $i;
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