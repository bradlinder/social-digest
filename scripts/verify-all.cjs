const fs = require('fs');
const phpParser = require('php-parser');

const parser = new phpParser.Engine({
  parser: { extractDoc: false, php7: true },
  ast: { withPositions: true }
});

const files = [
  'social-digest.php',
  'includes/helpers.php',
  'includes/api-clients.php',
  'includes/feed-builder.php',
  'includes/admin.php'
];

let allPassed = true;

for (const file of files) {
  try {
    const code = fs.readFileSync(file, 'utf8');
    parser.parseCode(code, file);
    console.log(`[PASS] ${file} - AST syntax valid`);
  } catch (err) {
    console.error(`[FAIL] ${file} - Syntax Error: ${err.message} on line ${err.lineNumber}`);
    allPassed = false;
  }
}

if (!allPassed) {
  process.exit(1);
} else {
  console.log('All PHP files passed AST syntax validation with 0 errors.');
}
