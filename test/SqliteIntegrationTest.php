<?php

/**
 * Runs Paris and Idiorm against a real (in-memory) SQLite database rather than
 * the mock PDO used by the other tests, covering the calls SOZOcms sites rely on.
 */
class SqliteIntegrationTest extends \PHPUnit\Framework\TestCase {

    public function setUp(): void {
        ORM::configure('sqlite::memory:');
        ORM::configure('logging', true);
        $db = ORM::get_db();
        $db->exec('CREATE TABLE category (id INTEGER PRIMARY KEY, name TEXT, sort INTEGER, deleted_at TEXT)');
        $db->exec('CREATE TABLE product (id INTEGER PRIMARY KEY, category_id INTEGER, name TEXT, price REAL)');
        $db->exec('CREATE TABLE product_detail (id INTEGER PRIMARY KEY, product_id INTEGER, description TEXT)');
        $db->exec('CREATE TABLE tag (id INTEGER PRIMARY KEY, name TEXT)');
        $db->exec('CREATE TABLE product_tag (id INTEGER PRIMARY KEY, product_id INTEGER, tag_id INTEGER)');

        $buffets = IntegrationCategory::create(array('name' => 'Buffets', 'sort' => 1));
        $buffets->save();
        IntegrationCategory::create(array('name' => 'Cakes', 'sort' => 2))->save();

        foreach (array(array('Sandwich', 2.5), array('Scone', 1.2)) as $row) {
            $product = IntegrationProduct::create();
            $product->category_id = $buffets->id;
            $product->name = $row[0];
            $product->price = $row[1];
            $product->save();
        }

        $db->exec("INSERT INTO product_detail (product_id, description) VALUES (1, 'Freshly made')");
        $db->exec("INSERT INTO tag (name) VALUES ('vegetarian'), ('popular')");
        $db->exec('INSERT INTO product_tag (product_id, tag_id) VALUES (1, 1), (1, 2), (2, 2)');
    }

    public function tearDown(): void {
        ORM::reset_config();
        ORM::reset_db();
    }

    public function testCreateAndFindOne() {
        $category = Model::factory('IntegrationCategory')->where('name', 'Buffets')->find_one();
        $this->assertSame('Buffets', $category->name);
        $this->assertEquals(1, $category->id());
        $this->assertFalse(Model::factory('IntegrationCategory')->where('name', 'Missing')->find_one());
    }

    public function testFindManyCountAndFindArray() {
        $this->assertCount(2, Model::factory('IntegrationProduct')->find_many());
        $this->assertEquals(1, Model::factory('IntegrationProduct')->where_gt('price', 2)->count());
        $rows = ORM::for_table('product')->order_by_desc('price')->find_array();
        $this->assertSame('Sandwich', $rows[0]['name']);
        $this->assertSame(array('id', 'category_id', 'name', 'price'), array_keys($rows[0]));
    }

    public function testWhereNullLikeAndOrder() {
        $rows = Model::factory('IntegrationCategory')
            ->where_null('deleted_at')
            ->where_like('name', '%a%')
            ->order_by_asc('sort')
            ->find_many();
        $this->assertSame(array('Cakes'), array_map(function ($row) { return $row->name; }, $rows));
        $this->assertEquals(2, ORM::for_table('category')->where_not_null('name')->count());
    }

    public function testRelations() {
        $buffets = Model::factory('IntegrationCategory')->find_one(1);
        $this->assertCount(2, $buffets->products()->find_many());

        $sandwich = Model::factory('IntegrationProduct')->find_one(1);
        $this->assertSame('Buffets', $sandwich->category()->find_one()->name);
        $this->assertSame('Freshly made', $sandwich->detail()->find_one()->description);

        $tags = $sandwich->tags()->order_by_asc('name')->find_many();
        $this->assertSame(array('popular', 'vegetarian'), array_map(function ($tag) { return $tag->name; }, $tags));
    }

    public function testSetExprAndUpdate() {
        $cakes = ORM::for_table('category')->find_one(2);
        $cakes->set_expr('sort', 'sort + 10');
        $cakes->save();
        $this->assertEquals(12, ORM::for_table('category')->find_one(2)->sort);
    }

    public function testSettingNullIsSaved() {
        $cakes = ORM::for_table('category')->find_one(2);
        $cakes->deleted_at = '2026-01-01';
        $cakes->save();
        $cakes = ORM::for_table('category')->find_one(2);
        $cakes->deleted_at = null;
        $this->assertTrue($cakes->is_dirty('deleted_at'));
        $cakes->save();
        $this->assertNull(ORM::for_table('category')->find_one(2)->deleted_at);
    }

    public function testArrayAccessAsArrayAndResultSet() {
        $buffets = ORM::for_table('category')->find_one(1);
        $this->assertTrue(isset($buffets['name']));
        $this->assertSame('Buffets', $buffets['name']);
        $this->assertSame(array('id' => 1, 'name' => 'Buffets'), $buffets->as_array('id', 'name'));

        $results = ORM::for_table('product')->find_result_set();
        $this->assertCount(2, $results);
        $names = array();
        foreach ($results as $row) {
            $names[] = $row->name;
        }
        $this->assertSame(array('Sandwich', 'Scone'), $names);
    }

    public function testRawQueryAndDelete() {
        $rows = ORM::for_table('product')->raw_query('SELECT * FROM product WHERE price > :p', array('p' => 2))->find_many();
        $this->assertCount(1, $rows);

        ORM::for_table('product')->where('name', 'Scone')->find_one()->delete();
        $this->assertStringContainsString('DELETE FROM', ORM::get_last_query());
        $this->assertEquals(1, ORM::for_table('product')->count());
    }
}

class IntegrationCategory extends Model {
    public static $_table = 'category';
    public function products() { return $this->has_many('IntegrationProduct', 'category_id'); }
}

class IntegrationProduct extends Model {
    public static $_table = 'product';
    public function category() { return $this->belongs_to('IntegrationCategory', 'category_id'); }
    public function detail() { return $this->has_one('IntegrationProductDetail', 'product_id'); }
    public function tags() { return $this->has_many_through('IntegrationTag', 'IntegrationProductTag', 'product_id', 'tag_id'); }
}

class IntegrationProductDetail extends Model {
    public static $_table = 'product_detail';
}

class IntegrationTag extends Model {
    public static $_table = 'tag';
}

class IntegrationProductTag extends Model {
    public static $_table = 'product_tag';
}
