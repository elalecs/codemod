<?php

class TestClass
{
    private string $name = 'Test';
    protected array $options = array('option1' => 'value1', 'option2' => 'value2', 'option3' => 'value3', 'option4' => '\'value4\'', 'option5' => '123');
    public function getName() : string
    {
        return $this->name;
    }
    use SomeTrait;
    protected $description = 'Test Description';
    public function setName(string $name) : void
    {
        $this->name = $name;
    }
    public function getOptions() : array
    {
        return $this->options;
    }
    use AnotherTrait;
    public $version = '"1.0"';
    public string $apiVersion = '2.0';
    public function getDescription() : string
    {
        return $this->description;
    }
}