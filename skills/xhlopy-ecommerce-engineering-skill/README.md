# XHlopy E-Commerce Engineering Skill

Portable agent skill package for coding agents that support the `SKILL.md` convention.

## Contents
- `SKILL.md` — primary instructions and mandatory workflow
- `references/` — frontend, backend, database, security, testing/release standards, roadmap, recommended technology stack, and confirmed hybrid marketplace architecture
- `templates/` — task plan and engineering handoff templates

## Installation
Copy the whole `xhlopy-ecommerce-engineering` directory into the skill directory supported by your coding agent. Common conventions include:
- Claude Code: `.claude/skills/xhlopy-ecommerce-engineering/`
- Other agents: use that agent's documented skills directory or workspace skill discovery mechanism.

Keep the folder name and `SKILL.md` together. Some agents require restarting/reloading the session to discover newly added skills.

## Important
This is an agent instruction skill, not a running MCP server and not an MCP server configuration. MCP provides tool connectivity; this skill provides reusable instructions for how an agent should work. Connect repository, database, browser, design, or deployment MCP servers separately and grant only the permissions needed.

The package includes a recommended technology stack for new development, but the agent must inspect the existing XHlopy repository and preserve its current stack unless a migration is explicitly approved. The confirmed product model is a hybrid multi-vendor marketplace with Super Admin, Seller, and Customer portals; sellers require approval before operating.
