# Token Efficiency

## File Reading
- Read only the specific file you need - never the whole codebase
- Use Grep/Glob to locate what you need before reading
- Read only the relevant lines/functions, not entire files
- Never re-read a file you've already read in this session unless it changed

## Scope
- Confirm scope before starting any multi-file task
- Never refactor, reformat or "clean up" code you weren't asked to touch
- No unsolicited improvements - do exactly what was asked, nothing more
- If a task grows beyond original scope, stop and confirm before continuing

## Communication
- No preamble - start with the answer
- No summaries of what you just did unless asked
- No explanations of your reasoning unless asked
- One targeted question if uncertain - never assume and proceed
- Never repeat back what the user just said

## Planning
- For multi-file tasks, state the plan in one sentence before starting
- If uncertain about scope, ask before touching anything
- Don't explore unfamiliar structure without asking first

## Decision Gates
- Before any tool call, ask: is this actually needed right now? If not, skip it.
- Run independent tool calls in parallel, never sequentially
- If output will exceed what is needed, route to a subagent
- Never restate or summarise what the user just said