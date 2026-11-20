import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const jsRoot = join(process.cwd(), 'resources', 'js');
const violations = [];

function walk(dir) {
    const entries = readdirSync(dir);
    const files = [];
    for (const entry of entries) {
        const full = join(dir, entry);
        if (statSync(full).isDirectory()) {
            files.push(...walk(full));
        } else if (/\.(ts|vue)$/.test(entry)) {
            files.push(full);
        }
    }
    return files;
}

const importRegex = /from\s+['"]([^'"]+)['"]/g;

// 1. Ensure shared/ never imports from @modules/ or ../modules/
const sharedDir = join(jsRoot, 'shared');
for (const file of walk(sharedDir)) {
    const content = readFileSync(file, 'utf8');
    let match;
    while ((match = importRegex.exec(content)) !== null) {
        const spec = match[1];
        if (spec.startsWith('@modules/') || spec.includes('/modules/')) {
            violations.push(
                `${relative(jsRoot, file)}: shared layer must not import module "${spec}"`,
            );
        }
    }
}

// 2. Ensure cross-module imports go only through a module's public barrel (@modules/<name>)
const modulesDir = join(jsRoot, 'modules');
for (const modName of readdirSync(modulesDir)) {
    const modPath = join(modulesDir, modName);
    if (!statSync(modPath).isDirectory()) continue;

    for (const file of walk(modPath)) {
        const content = readFileSync(file, 'utf8');
        let match;
        while ((match = importRegex.exec(content)) !== null) {
            const spec = match[1];
            const deepModuleMatch = spec.match(/^@modules\/([^/]+)\/(.+)$/);
            if (deepModuleMatch && deepModuleMatch[1] !== modName) {
                violations.push(
                    `${relative(jsRoot, file)}: module "${modName}" must import "@modules/${deepModuleMatch[1]}" via its public barrel instead of deep path "${spec}"`,
                );
            }
            if (spec.includes('../') && spec.includes('/modules/')) {
                violations.push(
                    `${relative(jsRoot, file)}: avoid relative cross-module import "${spec}"; use "@modules/<name>" barrel`,
                );
            }
        }
    }
}

if (violations.length > 0) {
    console.error('Frontend module boundary violations detected:');
    for (const v of violations) {
        console.error(` - ${v}`);
    }
    process.exit(1);
}

console.log('Frontend module boundaries verified: 0 violations.');
