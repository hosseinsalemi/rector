<?php

declare (strict_types=1);
namespace Ssch\TYPO3Rector\Rector\v11\v5;

use RectorPrefix20220410\Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPStan\Type\ObjectType;
use Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector;
use Rector\Core\Contract\PhpParser\NodePrinterInterface;
use Rector\Core\PhpParser\Parser\SimplePhpParser;
use Rector\Core\Rector\AbstractRector;
use Rector\FileSystemRector\ValueObject\AddedFileWithContent;
use Ssch\TYPO3Rector\Helper\FilesFinder;
use Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Symplify\SmartFileSystem\SmartFileInfo;
/**
 * @changelog https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Icon/Index.html
 * @see \Ssch\TYPO3Rector\Tests\Rector\v11\v5\RegisterIconToIconFileRector\RegisterIconToIconFileRectorTest
 */
final class RegisterIconToIconFileRector extends \Rector\Core\Rector\AbstractRector
{
    /**
     * @var string
     */
    private const REMOVE_EMPTY_LINES = '/^[ \\t]*[\\r\\n]+/m';
    /**
     * @var \Ssch\TYPO3Rector\Helper\FilesFinder
     */
    private $filesFinder;
    /**
     * @var \Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector
     */
    private $addIconsToReturnRector;
    /**
     * @var \Rector\Core\PhpParser\Parser\SimplePhpParser
     */
    private $simplePhpParser;
    /**
     * @var \Rector\Core\Contract\PhpParser\NodePrinterInterface
     */
    private $nodePrinter;
    /**
     * @var \Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector
     */
    private $removedAndAddedFilesCollector;
    public function __construct(\Ssch\TYPO3Rector\Helper\FilesFinder $filesFinder, \Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector $addIconsToReturnRector, \Rector\Core\PhpParser\Parser\SimplePhpParser $simplePhpParser, \Rector\Core\Contract\PhpParser\NodePrinterInterface $nodePrinter, \Rector\Core\Application\FileSystem\RemovedAndAddedFilesCollector $removedAndAddedFilesCollector)
    {
        $this->filesFinder = $filesFinder;
        $this->addIconsToReturnRector = $addIconsToReturnRector;
        $this->simplePhpParser = $simplePhpParser;
        $this->nodePrinter = $nodePrinter;
        $this->removedAndAddedFilesCollector = $removedAndAddedFilesCollector;
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Expr\MethodCall::class];
    }
    /**
     * @param MethodCall $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if (!$this->nodeTypeResolver->isMethodStaticCallOrClassMethodObjectType($node, new \PHPStan\Type\ObjectType('TYPO3\\CMS\\Core\\Imaging\\IconRegistry'))) {
            return null;
        }
        if (!$this->nodeNameResolver->isName($node->name, 'registerIcon')) {
            return null;
        }
        $currentSmartFileInfo = $this->file->getSmartFileInfo();
        $extEmConfFileInfo = $this->filesFinder->findExtEmConfRelativeFromGivenFileInfo($currentSmartFileInfo);
        if (!$extEmConfFileInfo instanceof \Symplify\SmartFileSystem\SmartFileInfo) {
            return null;
        }
        $extensionDirectory = \dirname($extEmConfFileInfo->getRealPath());
        $iconsFilePath = \sprintf('%s/Configuration/Icons.php', $extensionDirectory);
        $identifier = $this->valueResolver->getValue($node->args[0]->value);
        if (!\is_string($identifier)) {
            return null;
        }
        $options = $this->valueResolver->getValue($node->args[2]->value);
        $iconConfiguration = ['provider' => $node->args[1]->value];
        if (\is_array($options)) {
            $iconConfiguration = \array_merge($iconConfiguration, $options);
        }
        $this->addNewIconToIconsFile($iconsFilePath, $identifier, $iconConfiguration);
        $this->removeNode($node);
        return null;
    }
    /**
     * @codeCoverageIgnore
     */
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Generate or add registerIcon calls to Icons.php file', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample(<<<'CODE_SAMPLE'

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
$iconRegistry->registerIcon(
    'mybitmapicon',
    BitmapIconProvider::class,
    [
        'source' => 'EXT:my_extension/Resources/Public/Icons/mybitmap.png',
    ]
);
CODE_SAMPLE
, <<<'CODE_SAMPLE'
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);

// Add Icons.php file
CODE_SAMPLE
)]);
    }
    /**
     * @param Stmt[] $stmts
     */
    private function decorateNamesToFullyQualified(array $stmts) : void
    {
        // decorate nodes with names first
        $nameResolverNodeTraverser = new \PhpParser\NodeTraverser();
        $nameResolverNodeTraverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
        $nameResolverNodeTraverser->traverse($stmts);
    }
    /**
     * @param array<string, mixed> $iconConfiguration
     */
    private function addNewIconToIconsFile(string $iconsFilePath, string $iconIdentifier, array $iconConfiguration) : void
    {
        $addedFilesWithContent = $this->removedAndAddedFilesCollector->getAddedFilesWithContent();
        $existingIcons = null;
        foreach ($addedFilesWithContent as $addedFileWithContent) {
            if ($addedFileWithContent->getFilePath() === $iconsFilePath) {
                $existingIcons = $addedFileWithContent->getFileContent();
            }
        }
        if (\is_string($existingIcons)) {
            $stmts = $this->simplePhpParser->parseString($existingIcons);
        } else {
            $stmts = [new \PhpParser\Node\Stmt\Return_($this->nodeFactory->createArray([]))];
        }
        $this->decorateNamesToFullyQualified($stmts);
        $nodeTraverser = new \PhpParser\NodeTraverser();
        $this->addIconsToReturnRector->configure([\Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector::ICON_IDENTIFIER => $iconIdentifier, \Ssch\TYPO3Rector\Rector\v11\v5\RegisterIconToIconFileRector\AddIconsToReturnRector::ICON_CONFIGURATION => $iconConfiguration]);
        $nodeTraverser->addVisitor($this->addIconsToReturnRector);
        /** @var Stmt[] $stmts */
        $stmts = $nodeTraverser->traverse($stmts);
        $changedIconsContent = $this->nodePrinter->prettyPrintFile($stmts);
        $changedIconsContent = \RectorPrefix20220410\Nette\Utils\Strings::replace($changedIconsContent, self::REMOVE_EMPTY_LINES);
        $this->removedAndAddedFilesCollector->addAddedFile(new \Rector\FileSystemRector\ValueObject\AddedFileWithContent($iconsFilePath, $changedIconsContent));
    }
}
