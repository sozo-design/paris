<?php

class MagicMethodsTest extends \PHPUnit\Framework\TestCase {

    public function setUp(): void {
        // Set up the dummy database connection
        ORM::set_db(new MockPDO('sqlite::memory:'));

        // Enable logging
        ORM::configure('logging', true);
        
        Model::$auto_prefix_models = null;
    }

    public function tearDown(): void {
        ORM::configure('logging', false);
        ORM::set_db(null);

        Model::$auto_prefix_models = null;
    }

    public function testMagicMethodUnset() {
        $model = Model::factory("Simple")->create();
        $model->property = "test";
        unset($model->property);
        $this->assertFalse(isset($model->property));
        $this->assertTrue($model->get("property")!="test");
    }
}
