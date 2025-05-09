<?php

namespace App\Services;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class CodeParserService
{
    protected array $functions = [];
    protected array $statements = [];
    protected array $tokens = [];

    public function parse(string $code): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if (!$ast) {
            return ['error' => 'Parsing failed'];
        }

        $traverser = new NodeTraverser();
        $visitor = new AstVisitor();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getResults();
    }
}
