<?php

declare (strict_types=1);
namespace Ssch\TYPO3Rector\Rector\Migrations;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use Rector\Core\Configuration\RenamedClassesDataCollector;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\Util\StaticRectorStrings;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\Renaming\NodeManipulator\ClassRenamer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\SmartFileSystem\SmartFileInfo;
/**
 * @see \Ssch\TYPO3Rector\Tests\Rector\Migrations\RenameClassMapAliasRectorTest
 */
final class RenameClassMapAliasRector extends \Rector\Core\Rector\AbstractRector implements \Rector\Core\Contract\Rector\ConfigurableRectorInterface
{
    /**
     * @api
     * @var string
     */
    public const CLASS_ALIAS_MAPS = 'class_alias_maps';
    /**
     * @api
     * @var string
     */
    public const CLASSES_TO_SKIP = 'classes_to_skip';
    /**
     * @var array<string, string>
     */
    private $oldToNewClasses = [];
    /**
     * @var string[]
     */
    private $classesToSkip = [
        // can be string
        'language',
    ];
    /**
     * @var \Rector\Core\Configuration\RenamedClassesDataCollector
     */
    private $renamedClassesDataCollector;
    /**
     * @var \Rector\Renaming\NodeManipulator\ClassRenamer
     */
    private $classRenamer;
    public function __construct(\Rector\Core\Configuration\RenamedClassesDataCollector $renamedClassesDataCollector, \Rector\Renaming\NodeManipulator\ClassRenamer $classRenamer)
    {
        $this->renamedClassesDataCollector = $renamedClassesDataCollector;
        $this->classRenamer = $classRenamer;
    }
    /**
     * @codeCoverageIgnore
     */
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Replaces defined classes by new ones.', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample(<<<'CODE_SAMPLE'
namespace App;

use t3lib_div;

function someFunction()
{
    t3lib_div::makeInstance(\tx_cms_BackendLayout::class);
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
namespace App;

use TYPO3\CMS\Core\Utility\GeneralUtility;

function someFunction()
{
    GeneralUtility::makeInstance(\TYPO3\CMS\Backend\View\BackendLayoutView::class);
}
CODE_SAMPLE
, [self::CLASS_ALIAS_MAPS => 'config/Migrations/Code/ClassAliasMap.php'])]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Name::class, \PhpParser\Node\Stmt\Property::class, \PhpParser\Node\FunctionLike::class, \PhpParser\Node\Stmt\Expression::class, \PhpParser\Node\Stmt\ClassLike::class, \PhpParser\Node\Stmt\Namespace_::class, \PhpParser\Node\Scalar\String_::class];
    }
    /**
     * @param Name|FunctionLike|Property|Name|Expression|String_ $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if ($node instanceof \PhpParser\Node\Scalar\String_) {
            return $this->stringClassNameToClassConstantRectorIfPossible($node);
        }
        return $this->classRenamer->renameNode($node, $this->oldToNewClasses);
    }
    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration) : void
    {
        $classAliasMaps = $configuration[self::CLASS_ALIAS_MAPS] ?? [];
        foreach ($classAliasMaps as $file) {
            $filePath = new \Symplify\SmartFileSystem\SmartFileInfo($file);
            $classAliasMap = (require $filePath->getRealPath());
            foreach ($classAliasMap as $oldClass => $newClass) {
                $this->oldToNewClasses[$oldClass] = $newClass;
            }
        }
        if ([] !== $this->oldToNewClasses) {
            $this->renamedClassesDataCollector->addOldToNewClasses($this->oldToNewClasses);
        }
        if (isset($configuration[self::CLASSES_TO_SKIP])) {
            $this->classesToSkip = $configuration[self::CLASSES_TO_SKIP];
        }
    }
    private function stringClassNameToClassConstantRectorIfPossible(\PhpParser\Node\Scalar\String_ $node) : ?\PhpParser\Node
    {
        if (!$this->isAtLeastPhpVersion(\Rector\Core\ValueObject\PhpVersionFeature::CLASSNAME_CONSTANT)) {
            return null;
        }
        $classLikeName = $node->value;
        // remove leading slash
        $classLikeName = \ltrim($classLikeName, '\\');
        if ('' === $classLikeName) {
            return null;
        }
        if (!\array_key_exists($classLikeName, $this->oldToNewClasses)) {
            return null;
        }
        if (\Rector\Core\Util\StaticRectorStrings::isInArrayInsensitive($classLikeName, $this->classesToSkip)) {
            return null;
        }
        $newClassName = $this->oldToNewClasses[$classLikeName];
        return $this->nodeFactory->createClassConstReference($newClassName);
    }
}
