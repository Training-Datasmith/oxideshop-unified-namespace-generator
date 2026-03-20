<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */
declare (strict_types=1);
namespace Oxid_Esales\Unified_Name_Space_Generator;

use Filesystem_Iterator;
use Oxid_Esales\Eshop_Community\Internal\Framework\Edition\Edition_Resolver;
use Oxid_Esales\Unified_Name_Space_Generator\Exceptions\File_System_Compatibility_Exception;
use Oxid_Esales\Unified_Name_Space_Generator\Exceptions\Output_Directory_Validation_Exception;
use Oxid_Esales\Unified_Name_Space_Generator\Exceptions\Permission_Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\Filesystem_Loader;
class Generator
{
    public function __construct(private readonly Unified_Name_Space_Class_Map_Provider $unified_name_space_class_map_provider, private readonly string $output_directory = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR, private readonly string $template_dir = __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR, private readonly Filesystem $file_system = new Filesystem())
    {
        $this->validate_output_directory_permissions();
    }
    public function cleanup_output_directory(): void
    {
        $directory_iterator = new \Recursive_Directory_Iterator($this->output_directory, Filesystem_Iterator::SKIP_DOTS);
        foreach ($directory_iterator as $current) {
            if (!str_contains($current->get_filename(), '.gitkeep')) {
                $this->file_system->remove($current->get_pathname());
            }
        }
    }
    public function generate(): void
    {
        $this->generate_class_files($this->unified_name_space_class_map_provider->get_class_map());
    }
    protected function generate_class_files(array $class_map): void
    {
        $backwards_compatibility_map = (new Backwards_Compatibility_Class_Map_Provider())->get_class_map();
        $unified_namespace_array = $this->get_unified_namespace_array($class_map);
        $this->validate_unified_namespace_array($unified_namespace_array);
        foreach ($unified_namespace_array as $unified_sub_namespace => $edition_class_descriptions) {
            $this->build_sub_namespace($unified_sub_namespace, $edition_class_descriptions, $backwards_compatibility_map);
        }
    }
    protected function get_unified_namespace_array(array $class_map): array
    {
        $unified_name_space = [];
        foreach ($class_map as $fully_qualified_unified_class => $edition_class_description) {
            $parts = explode('\\', (string) $fully_qualified_unified_class);
            $short_unified_class_name = array_pop($parts);
            $this->validate_short_unified_class_name($short_unified_class_name, $fully_qualified_unified_class);
            $unified_sub_namespace = implode('\\', $parts);
            $this->validate_unified_namespace($unified_sub_namespace, $fully_qualified_unified_class);
            $this->validate_edition_class_description($edition_class_description);
            $unified_name_space[$unified_sub_namespace][] = ['isAbstract' => $edition_class_description['isAbstract'], 'isInterface' => $edition_class_description['isInterface'], 'isDeprecated' => $edition_class_description['isDeprecated'], 'shortUnifiedClassName' => $short_unified_class_name, 'editionClassName' => $edition_class_description['editionClassName']];
        }
        return $unified_name_space;
    }
    protected function build_sub_namespace(string $unified_sub_namespace, array $edition_class_descriptions, array $backwards_compatibility_map): void
    {
        $sub_namespace_path = $this->create_unified_namespace_sub_directory($unified_sub_namespace);
        foreach ($edition_class_descriptions as $edition_class_description) {
            $short_unified_class_name = $edition_class_description['shortUnifiedClassName'];
            $file_path = Path::join($sub_namespace_path, $short_unified_class_name . '.php');
            $fully_qualified_unified_class = '\\' . trim($unified_sub_namespace . '\\' . $short_unified_class_name, '\\');
            $backwards_compatible_class = $this->get_backwards_compatible_class($fully_qualified_unified_class, $backwards_compatibility_map);
            $content = $this->render_content($unified_sub_namespace, $edition_class_description, $fully_qualified_unified_class, $backwards_compatible_class);
            $this->write_file($file_path, $content);
        }
    }
    private function get_backwards_compatible_class(string $fully_qualified_unified_class, array $backwards_compatibility_map): ?string
    {
        $backwards_compatibility_map_index = trim($fully_qualified_unified_class, '\\');
        return $backwards_compatibility_map[$backwards_compatibility_map_index] ?? null;
    }
    protected function render_content(string $unified_sub_namespace, array $edition_class_description, string $fully_qualified_unified_class, ?string $backwards_compatible_class): string
    {
        return $this->get_twig()->render('class_file_template.html.twig', ['shopEdition' => (new Edition_Resolver())->get_edition()->value, 'class' => $edition_class_description, 'namespace' => $unified_sub_namespace, 'fullyQualifiedUnifiedClass' => $fully_qualified_unified_class, 'backwardsCompatibleClass' => $backwards_compatible_class]);
    }
    protected function write_file(string $file_path, string $content): void
    {
        $this->validate_output_directory_permissions();
        $current_directory = dirname($file_path);
        if (!is_writable($current_directory)) {
            throw new Permission_Exception(\sprintf('Could not create file %s. The directory %s is not writable for user "%s".' . 'Please fix the permissions on this directory and run this script again.', $file_path, $current_directory, get_current_user()));
        }
        if (file_exists($file_path) && !is_writable($file_path) || !$file_handle = fopen($file_path, 'wb')) {
            throw new File_System_Compatibility_Exception(\sprintf('Could not open file handle for %s. There might be a problem with your file system.' . 'Try to solve this problem and run this script again.', $file_path));
        }
        $result = fwrite($file_handle, $content);
        fclose($file_handle);
        if ($result === false) {
            throw new \Exception(\sprintf('Could not create file %s', $file_path));
        }
        if ($result === 0) {
            throw new \Exception(\sprintf('Created empty file %s', $file_path));
        }
    }
    protected function validate_unified_namespace(string $unified_sub_namespace, string $fully_qualified_unified_class): void
    {
        if (!$unified_sub_namespace) {
            throw new \Exception('Could not extract unified sub namespace from string ' . $fully_qualified_unified_class);
        }
    }
    protected function validate_edition_class_description(array $edition_class_description): void
    {
        $expected_keys = ['isAbstract', 'isInterface', 'editionClassName', 'isDeprecated'];
        $message = 'Edition class description has a wrong layout. ' . 'It must be a non-empty array with the following keys ' . implode(',', $expected_keys) . ' ';
        if (empty($edition_class_description)) {
            throw new \Exception($message);
        }
        $actual_keys = array_keys($edition_class_description);
        sort($expected_keys);
        sort($actual_keys);
        if ($expected_keys != $actual_keys) {
            $message .= ' Actual edition class description is ' . var_export($edition_class_description, true);
            throw new \Exception($message);
        }
        // Validate that editionClassName is a fully-qualified PHP class name (backslash-separated identifiers)
        // to prevent code injection when it is written into generated PHP files via the Twig template.
        $edition_class_name = (string) $edition_class_description['editionClassName'];
        $identifier_pattern = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';
        if (!preg_match('/^\\\\?' . $identifier_pattern . '(\\\\' . $identifier_pattern . ')*$/', $edition_class_name)) {
            throw new \Exception('Edition class name "' . $edition_class_name . '" contains invalid characters. ' . 'Only valid PHP fully-qualified class name characters are allowed.');
        }
    }
    protected function validate_short_unified_class_name(string $short_unified_class_name, string $fully_qualified_unified_class): void
    {
        if (!$short_unified_class_name) {
            throw new \Exception('Could not extract short unified a class name from string ' . $fully_qualified_unified_class);
        }
        // Ensure the class name is a valid PHP identifier to prevent code injection
        // when it is written verbatim into generated PHP files via the Twig template.
        if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $short_unified_class_name)) {
            throw new \Exception('Short unified class name "' . $short_unified_class_name . '" contains invalid characters. ' . 'Only valid PHP identifier characters are allowed.');
        }
    }
    protected function validate_unified_namespace_array(array $unified_namespace_array): void
    {
        if (empty($unified_namespace_array)) {
            throw new \Exception('No unified namespace found');
        }
    }
    protected function validate_output_directory_permissions(): void
    {
        if (!is_dir($this->output_directory)) {
            throw new Output_Directory_Validation_Exception(\sprintf('The directory "%s" where the class files have to be written to does not exist. Please ' . 'create the directory "%s" with write permissions for the user "%s" and run this script again', $this->output_directory, $this->output_directory, get_current_user()));
        }
        if (!is_writable($this->output_directory)) {
            throw new Output_Directory_Validation_Exception(\sprintf('The directory "%s" where the class files have to be written to is not writable for user ' . '"%s". Please fix the permissions on this directory and run this script again', realpath($this->output_directory), get_current_user()));
        }
    }
    protected function create_unified_namespace_sub_directory(string $unified_sub_namespace): string
    {
        $this->validate_output_directory_permissions();
        $unified_sub_namespace_path = Path::join($this->output_directory, $unified_sub_namespace);
        $this->file_system->mkdir($unified_sub_namespace_path, 0755);
        return $unified_sub_namespace_path;
    }
    protected function get_twig(): Environment
    {
        $loader = new Filesystem_Loader($this->template_dir);
        return new Environment($loader);
    }
}