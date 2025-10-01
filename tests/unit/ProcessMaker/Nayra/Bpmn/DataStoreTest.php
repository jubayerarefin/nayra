<?php

namespace ProcessMaker\Nayra\Bpmn;

use ProcessMaker\Nayra\Contracts\Bpmn\ActivityInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\DataStoreInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\ProcessInterface;
use ProcessMaker\Nayra\Contracts\Bpmn\StateInterface;
use Tests\Feature\Engine\EngineTestCase;

/**
 * Tests for the DataStore class
 */
class DataStoreTest extends EngineTestCase
{
    /**
     * Tests that setters and getters are working properly
     */
    public function testDataStoreSettersAndGetters()
    {
        // Create the objects that will be set in the data store
        $dataStore = $this->repository->createDataStore();
        $process = $this->repository->createProcess();
        $process->setRepository($this->repository);
        $dummyActivity = $this->repository->createActivity();
        $dummyActivity->setRepository($this->repository);
        $state = $this->repository->createState($dummyActivity, '');

        // Set process and state object to the data store
        $dataStore->setOwnerProcess($process);

        //Assertion: The get process must be equal to the set process
        $this->assertEquals($process, $dataStore->getOwnerProcess());

        //Assertion: the data store should have a non initialized item subject
        $this->assertNull($dataStore->getItemSubject());
    }

    /**
     * Tests the setDotData function with various scenarios
     */
    public function testSetDotData()
    {
        $dataStore = $this->repository->createDataStore();

        // Test simple dot notation
        $dataStore->setDotData('user.name', 'John Doe');
        $userData = $dataStore->getData('user');
        $this->assertEquals('John Doe', $userData['name']);

        // Test nested dot notation
        $dataStore->setDotData('user.profile.email', 'john@example.com');
        $userData = $dataStore->getData('user');
        $this->assertEquals('john@example.com', $userData['profile']['email']);

        // Test deeply nested structure
        $dataStore->setDotData('company.departments.engineering.team.lead', 'Jane Smith');
        $companyData = $dataStore->getData('company');
        $this->assertEquals('Jane Smith', $companyData['departments']['engineering']['team']['lead']);

        // Test numeric keys
        $dataStore->setDotData('items.0.name', 'First Item');
        $dataStore->setDotData('items.1.name', 'Second Item');
        $itemsData = $dataStore->getData('items');
        $this->assertEquals('First Item', $itemsData[0]['name']);
        $this->assertEquals('Second Item', $itemsData[1]['name']);

        // Test numeric final key (to cover the is_numeric check for finalKey)
        $dataStore->setDotData('scores.0', 100);
        $dataStore->setDotData('scores.1', 200);
        $scoresData = $dataStore->getData('scores');
        $this->assertEquals(100, $scoresData[0]);
        $this->assertEquals(200, $scoresData[1]);

        // Test overwriting existing values
        $dataStore->setDotData('user.name', 'Jane Doe');
        $userData = $dataStore->getData('user');
        $this->assertEquals('Jane Doe', $userData['name']);

        // Test setting complex values
        $complexValue = ['type' => 'admin', 'permissions' => ['read', 'write']];
        $dataStore->setDotData('user.role', $complexValue);
        $userData = $dataStore->getData('user');
        $this->assertEquals($complexValue, $userData['role']);

        // Test setting null values
        $dataStore->setDotData('user.middleName', null);
        $userData = $dataStore->getData('user');
        $this->assertNull($userData['middleName']);

        // Test setting boolean values
        $dataStore->setDotData('user.active', true);
        $userData = $dataStore->getData('user');
        $this->assertTrue($userData['active']);

        // Test setting numeric values
        $dataStore->setDotData('user.age', 30);
        $userData = $dataStore->getData('user');
        $this->assertEquals(30, $userData['age']);

        // Test that the method returns the data store instance for chaining
        $result = $dataStore->setDotData('test.chain', 'value');
        $this->assertSame($dataStore, $result);

        // Verify the complete data structure
        $expectedData = [
            'user' => [
                'name' => 'Jane Doe',
                'profile' => [
                    'email' => 'john@example.com'
                ],
                'role' => [
                    'type' => 'admin',
                    'permissions' => ['read', 'write']
                ],
                'middleName' => null,
                'active' => true,
                'age' => 30
            ],
            'company' => [
                'departments' => [
                    'engineering' => [
                        'team' => [
                            'lead' => 'Jane Smith'
                        ]
                    ]
                ]
            ],
            'items' => [
                0 => [
                    'name' => 'First Item'
                ],
                1 => [
                    'name' => 'Second Item'
                ]
            ],
            'scores' => [
                0 => 100,
                1 => 200
            ],
            'test' => [
                'chain' => 'value'
            ]
        ];

        $this->assertEquals($expectedData, $dataStore->getData());
    }

    /**
     * Tests the getDotData function with various scenarios
     */
    public function testGetDotData()
    {
        $dataStore = $this->repository->createDataStore();

        // Set up test data using setDotData
        $dataStore->setDotData('user.name', 'John Doe');
        $dataStore->setDotData('user.profile.email', 'john@example.com');
        $dataStore->setDotData('user.profile.age', 30);
        $dataStore->setDotData('user.active', true);
        $dataStore->setDotData('user.role', ['type' => 'admin', 'permissions' => ['read', 'write']]);
        $dataStore->setDotData('company.departments.engineering.team.lead', 'Jane Smith');
        $dataStore->setDotData('items.0.name', 'First Item');
        $dataStore->setDotData('items.1.name', 'Second Item');
        $dataStore->setDotData('scores.0', 100);
        $dataStore->setDotData('scores.1', 200);
        $dataStore->setDotData('user.middleName', null);

        // Test simple dot notation retrieval
        $this->assertEquals('John Doe', $dataStore->getDotData('user.name'));
        $this->assertEquals('john@example.com', $dataStore->getDotData('user.profile.email'));
        $this->assertEquals(30, $dataStore->getDotData('user.profile.age'));
        $this->assertTrue($dataStore->getDotData('user.active'));

        // Test nested dot notation retrieval
        $this->assertEquals('Jane Smith', $dataStore->getDotData('company.departments.engineering.team.lead'));

        // Test numeric keys
        $this->assertEquals('First Item', $dataStore->getDotData('items.0.name'));
        $this->assertEquals('Second Item', $dataStore->getDotData('items.1.name'));

        // Test numeric final keys
        $this->assertEquals(100, $dataStore->getDotData('scores.0'));
        $this->assertEquals(200, $dataStore->getDotData('scores.1'));

        // Test complex values
        $expectedRole = ['type' => 'admin', 'permissions' => ['read', 'write']];
        $this->assertEquals($expectedRole, $dataStore->getDotData('user.role'));

        // Test null values
        $this->assertNull($dataStore->getDotData('user.middleName'));

        // Test non-existent paths with default values
        $this->assertNull($dataStore->getDotData('non.existent.path'));
        $this->assertEquals('default', $dataStore->getDotData('non.existent.path', 'default'));
        $this->assertEquals('fallback', $dataStore->getDotData('user.nonExistent', 'fallback'));

        // Test partial path that doesn't exist
        $this->assertNull($dataStore->getDotData('user.profile.nonExistent'));
        $this->assertEquals('not found', $dataStore->getDotData('user.profile.nonExistent', 'not found'));

        // Test deeply nested non-existent path
        $this->assertNull($dataStore->getDotData('company.departments.marketing.team.lead'));
        $this->assertEquals('no lead', $dataStore->getDotData('company.departments.marketing.team.lead', 'no lead'));

        // Test numeric key that doesn't exist
        $this->assertNull($dataStore->getDotData('items.2.name'));
        $this->assertEquals('missing', $dataStore->getDotData('items.2.name', 'missing'));

        // Test empty path
        $this->assertNull($dataStore->getDotData(''));
        $this->assertEquals('empty', $dataStore->getDotData('', 'empty'));

        // Test single key that exists (returns the entire user array)
        $userData = $dataStore->getDotData('user');
        $this->assertIsArray($userData);
        $this->assertEquals('John Doe', $userData['name']);
        $this->assertEquals('john@example.com', $userData['profile']['email']);
        $this->assertEquals(30, $userData['profile']['age']);
        $this->assertTrue($userData['active']);
        $this->assertNull($userData['middleName']);

        // Test single key that doesn't exist
        $this->assertNull($dataStore->getDotData('nonexistent'));
        $this->assertEquals('not found', $dataStore->getDotData('nonexistent', 'not found'));

        // Test boolean false value
        $dataStore->setDotData('user.disabled', false);
        $this->assertFalse($dataStore->getDotData('user.disabled'));

        // Test zero value
        $dataStore->setDotData('user.score', 0);
        $this->assertEquals(0, $dataStore->getDotData('user.score'));

        // Test empty string
        $dataStore->setDotData('user.description', '');
        $this->assertEquals('', $dataStore->getDotData('user.description'));

        // Test empty array
        $dataStore->setDotData('user.tags', []);
        $this->assertEquals([], $dataStore->getDotData('user.tags'));
    }
}
