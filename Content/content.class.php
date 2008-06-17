<?php
  class Content {
  
    /************************************************************************/
    /* Properties                                                           */
    /************************************************************************/
    
    public $id = null;
    public $title = "";
    public $permalink = "";
    public $body = "";
    public $created_at = null;
    public $updated_at = null;
    public $deleted = false;
    protected $parent_id = null;
    protected $is_new = true;
    protected $errors = array();

    /************************************************************************/
    /* Methods                                                              */
    /************************************************************************/
    
    /*
    Saves the Content item. If a permalink isn't set, then one will be generated
    based on the title. If the item is new, it is inserted into the DB and the
    timestamps updated. If it already exists then it will be updated and the
    updated_at timestamp updated.
    
    Validation is performed and any errors can be read using the errors() which
    returns an array. All data inserted is escaped using mysql_real_escape_string.
    
    @return               True if successful, false otherwise
    */
    public function save() {
      if ($this->is_new) {
        if ($this->permalink == "") {
          $this->permalink = Content::make_permalink($this->title);
        }
        if ($this->validate()) {
          $escaped = $this->escape();
          $parent_id = "NULL";
          if ($this->parent_id) {
            $parent_id = $this->parent_id;
          }
          $deleted = "false";
          if ($this->deleted) {
            $deleted = "true";
          }
          $result = mysql_query("INSERT INTO `content` (`title`, `permalink`, `body`, `created_at`, `updated_at`, `deleted`, `parent_id`)
                                      VALUES ('{$escaped['title']}', '{$escaped['permalink']}', '{$escaped['body']}', NOW(), NOW(), {$deleted}, {$parent_id})");
          if ($result !== false) {
            $this->id = mysql_insert_id();
            $this->is_new = false;
            return true;
          }
          else {
            $errors[] = "Error saving content to database";
          }
        }
        else {
          return false;
        }
      }
      else {
        if ($this->validate()) {
          $escaped = $this->escape();
          $parent_id = "NULL";
          if ($this->parent_id) {
            $parent_id = $this->parent_id;
          }
          $deleted = "false";
          if ($this->deleted) {
            $deleted = "true";
          }
          $result = mysql_query("UPDATE `content`
                                    SET `title`= '{$escaped['title']}',
                                        `permalink` = '{$escaped['permalink']}',
                                        `body` = '{$escaped['body']}',
                                        `updated_at` = NOW(),
                                        `deleted` = $this->deleted,
                                        `parent_id` = $parent_id
                                  WHERE `id` = '{$escaped['id']}'
                                  LIMIT 1");
          if ($result) {
            $this->updated_at = time();
            return true;
          }
          else {
            $errors[] = "Error saving content to database";
            return false;
          }
        }
        else {
          return false;
        }
      }
    }
    
    /*
    Returns an array of errors for this Content item. This is populated by saving
    and validation. If there are no errors, it returns false.
    
    @return               False if there are no errors, otherwise an array of errors
    */
    public function errors() {
      if (count($this->errors) == 0) {
        return false;
      }
      else {
        return $this->errors;
      }
    }
    
    /*
    Validates the properties of this object. It is called before saving and runs
    a number of tests on the properties. If any are found to be invalid, it
    returns false and adds errors to the errors property which can be inspected
    using the errors(); method.
    
    @return               True on success, false on failure
    */
    public function validate() {
      $return = true;
      if ($this->title == "") {
        $this->errors[] = "You must enter a title";
        $return = false;
      }
      if ($this->permalink == "") {
        $this->errors[] = "You must enter a permalink";
        $return = false;
      }
      if (strlen($this->permalink) > 128) {
        $this->errors[] = "The permalink cannot be longer than 128 characters";
        $return = false;
      }
      if (preg_match("([^a-z0-9-])", $this->permalink)) {
        $this->errors[] = "The permalink can only contain lowercase letters, numbers and hyphens";
        $return = false;
      }
      $permalink = mysql_real_escape_string($this->permalink);
      $and_not_self = "";
      if ($this->id) {
        $id = mysql_real_escape_string($this->id);
        $and_not_self = " AND `id` != '{$id}'";
      }
      $result = mysql_query("SELECT `id` FROM `content` WHERE `permalink` = '{$permalink}'{$and_not_self}");
      if (mysql_num_rows($result) > 0) {
        $this->errors[] = "The permalink must be unique";
        $return = false;
      }
      if ($this->body == "") {
        $this->errors[] = "You must enter some content";
        $return = false;
      }
      
      return $return;
    }
    
    /*
    Soft deletes the content item and updates all child items so they become
    children of this item's parent.
    */
    public function destroy() {
      $this->deleted = true;
      $id = mysql_real_escape_string($this->id);
      mysql_query("UPDATE `content` SET `deleted` = true WHERE `id` = '{$id}' LIMIT 1;");
      foreach($this->get_children() as $child) {
        $child->parent = $this->parent;
        $child->save();
      }
    }
    
    /*
    Returns an array of all child content items.
    
    @return               An array of child content items
    */
    public function get_children() {
      $id = mysql_real_escape($this->id);
      $result = mysql_query("SELECT `id`, `title`, `permalink`, `body`, UNIX_TIMESTAMP(`created_at`), UNIX_TIMESTAMP(`updated_at`), `deleted`, `parent_id`
                               FROM `content`
                              WHERE `parent_id`='{$id}' AND `deleted` = false");
      return Content::load_from_results($result);
    }
    
    /*
    Returns the parent item (if it exists)
    
    @return               The parent Content item
    */
    public function get_parent() {
      $parent = mysql_real_escape($this->parent_id);
      $result = mysql_query("SELECT `id`, `title`, `permalink`, `body`, UNIX_TIMESTAMP(`created_at`), UNIX_TIMESTAMP(`updated_at`), `deleted`, `parent_id`
                               FROM `content`
                              WHERE `parent_id`='{$id}' AND `deleted` = false");
      return Content::load_from_results($result);
    }
    
    /*
    Sets the parent content item of this content item.
    
    @param    parent      Either a Content object to be the parent, or null
    @return               True on success, false on failure
    */
    public function set_parent($parent = null) {
      if ($parent = null) {
        $this->parent_id = null;
      }
      else {
        $this->parent_id = $parent->$id;
      }
      $id = mysql_real_escape_string($this->id);
      $parent_id = mysql_real_escape_string($this->parent_id);
      return mysql_query("UPDATE `content` SET `parent_id`='{$parent_id}' WHERE `id`='{$id} LIMIT 1'");
    }
    
    /*
    Escapes properties for the class and returns an associative array
    
    @return               Associative array of properties and escaped values
    */
    protected function escape() {
      $return = array();
      $return['title'] = mysql_real_escape_string($this->title);
      $return['permalink'] = mysql_real_escape_string($this->permalink);
      $return['body'] = mysql_real_escape_string($this->body);
      $return['id'] = mysql_real_escape_string($this->id);
      return $return;
    }
    
    /************************************************************************/
    /* Static Methods                                                       */
    /************************************************************************/
    
    /*
    Given a permalink, it returns the content item associated.
    
    @param    permalink   The permalink of the content item
    @return               The content item, or null
    */
    public static function find_by_permalink($permalink) {
      $permalink = mysql_real_escape_string($permalink);
      $result = mysql_query("SELECT `id`, `title`, `permalink`, `body`, UNIX_TIMESTAMP(`created_at`), UNIX_TIMESTAMP(`updated_at`), `deleted`, `parent_id`
                               FROM `content`
                              WHERE `permalink`='{$permalink}' AND `deleted` = false
                              LIMIT 1");
      return Content::load_from_results($result);
    }
    
    /* Given an ID, it returns the content item associated.
    
    @param    id          The ID of the content item
    @return               The content item, or null
    */
    public static function find_by_id($id) {
      $id = mysql_real_escape_string($id);
      $result = mysql_query("SELECT `id`, `title`, `permalink`, `body`, UNIX_TIMESTAMP(`created_at`), UNIX_TIMESTAMP(`updated_at`), `deleted`, `parent_id`
                               FROM `content`
                              WHERE `id`='{$id}' AND `deleted` = false
                              LIMIT 1");
      return Content::load_from_results($result);
    }

    /*
    Given a where clause and optional order, it returns either first content
    item or if 'all' is provided, all matching content items.
    
    @param    id          The ID of the content item
    @param    order       The order to return the items in
    @param    all         If true, return all items matching
    @return               The content item, array of items, or null
    */
    public static function find($where, $order = null, $all = false) {
      $limit = "LIMIT 1";
      if ($all) {
        $limit = "";
      }
      $order_by = "";
      if ($order) {
        $order_by = "ORDER BY {$order}";
      }
      $result = mysql_query("SELECT `id`, `title`, `permalink`, `body`, UNIX_TIMESTAMP(`created_at`), UNIX_TIMESTAMP(`updated_at`), `deleted`, `parent_id`
                               FROM `content`
                              WHERE {$where}
                              {$order_by}
                              {$limit}");
      return Content::load_from_results($result);
    }
    
    /*
    Given a where clause and optional order, it returns all matching content
    items. Is a wrapper for Content::find();
    
    @param    id          The ID of the content item
    @param    order       The order to return the items in
    @return               The content item, array of items, or null
    */
    public static function find_all($where, $order = null) {
      return Content::find($where, $order, true);
    }

    /*
    Given a mysql result set, it will return either the Content item or null.
    
    @param    result      The MySQL result set
    @return               An array of content items, a single item or null
    */
    protected static function load_from_results($result) {
      $return = null;
      while ($row = mysql_fetch_array($result)) {
        $content = new Content();
        $content->id = $row['id'];
        $content->title = $row['title'];
        $content->permalink = $row['permalink'];
        $content->body = $row['body'];
        $content->created_at = $row['created_at'];
        $content->updated_at = $row['updated_at'];
        $content->deleted = $row['deleted'];
        $content->parent_id = $row['parent_id'];
        $content->is_new = false;
        $return[] = $content;
      }
      if (count($return) == 1) {
        return $return[0];
      }
      return $return;
    }
    
    /*
    Takes a string and converts it to lowercase, replaces spaces with hypens and
    removes anything that's not a hyphen, lowercase or a number. It then trims
    it to 128 characters to be a permalink.
    
    @param    input       The string to turn into a permalink
    @return               The permalink
    */
    protected static function make_permalink($input) {
      $input = strtolower($input);
      $patterns = array("/ /", "/[^a-z0-9-]/i");
      $replacements = array("-", "");
      $output = preg_replace($patterns, $replacements, $input);
      return substr($output,0, 128);
    }
  }
?>