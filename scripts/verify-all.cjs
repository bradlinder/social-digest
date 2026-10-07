const fs = require('fs');
const path = require('path');
const phpParser = require('php-parser');

const parser = new phpParser.Engine({
  parser: { extractDoc: false, php7: true },
  ast: { withPositions: true }
});

function findPhpFiles(dir) {
  let results = [];
  if (!fs.existsSync(dir)) return results;
  const list = fs.readdirSync(dir);
  for (const item of list) {
    if (item.startsWith('.') || item === 'node_modules' || item === 'vendor') continue;
    const fullPath = path.join(dir, item);
    const stat = fs.statSync(fullPath);
    if (stat.isDirectory()) {
      results = results.concat(findPhpFiles(fullPath));
    } else if (item.endsWith('.php') && !item.endsWith('.bak')) {
      results.push(fullPath);
    }
  }
  return results;
}

const files = [];
if (fs.existsSync('social-digest.php')) {
  files.push('social-digest.php');
}
files.push(...findPhpFiles('includes'));

console.log(`Discovered ${files.length} PHP file(s) for AST verification:`);
files.forEach(f => console.log(` - ${f}`));

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
  console.log(`All ${files.length} PHP files passed AST syntax validation with 0 errors.`);
}

