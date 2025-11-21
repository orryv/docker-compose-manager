# AGENTS.md

## About this project

This is a PHP library to manage multiple Docker Compose configurations programmatically. It allows you to start, stop, inspect, and manage Docker Compose projects using a simple PHP interface.

## Project features

- Manage multiple Docker Compose files
- Start or restart services (from Docker Compose files or PHP arrays), including rebuilding containers, images, and volumes
- Stop or remove services (from Docker Compose files or from container names), including removing images and volumes
- Additional Docker management actions are out of scope for now

## Setup (what the agent needs to run)

- PHP 8.2 or higher
- Run `composer install` to install dependencies
- For local development and unit tests, Docker is **not** required. Docker-dependent tests will run in the CI pipeline or on a user environment with Docker installed.
- Run tests with `composer test:unit` (unit tests must not require Docker)

## Code style

- Use single quotes where possible
- Follow SOLID principles with a focus on testability and maintainability
- Use a test-driven development (TDD) approach
- Add Docblocks for all classes and methods
- PHP classes go in `src/`, tests in `tests/`, and documentation in `Docs/`

## Development cycle

When implementing new features or changes, work in cycles:

1. **Senior Project and Code Designer**
2. **Senior Code Implementer**
3. **Senior Documentation Writer**
4. **Senior Code Reviewer**
5. Back to the **Senior Code Implementer** if updates are needed, or finish (commit) if approved.

When the review passes, the feature or change is done and the cycle ends. If the review does **not** pass, the work goes back to the Senior Code Implementer for fixes, and then again to the Senior Documentation Writer for documentation updates, and then back to the Senior Code Reviewer for another review. This cycle continues until the code is approved.

Each role should leave a short summary and clear notes for the next agent, so they can understand the context and any important details and know what to focus on.

### Senior Project and Code Designer

- Plan the architecture and design of new features or improvements.
- Adapt to the style of the already written code.
- If a concept/design is given, make the final product resemble it as much as possible.
- Write specifications for the required feature or improvement.
- Think about important improvements or forgotten details.
- Review code for adherence to design principles and best practices.
- Provide guidance on how to implement (or what to change in) the required code and tests. (We use TDD, so tests are important.)
- Decide whether the code can be committed (everything is implemented correctly, tests are passing, code is clean and maintainable).  
  - If yes: approve the change.  
  - If no: pass it back to the Senior Code Implementer with concrete recommendations.

### Senior Code Implementer

1. Write the tests for the required feature or improvement.
2. Implement the code to make the tests pass.
3. Ensure code quality and adherence to design principles.
4. Refactor code as needed for maintainability and performance.
5. Run all tests to ensure nothing is broken.
6. Fix code or tests if something is broken.
7. Provide guidance on changes and new features so the Documentation Writer can update the documentation accordingly.
8. Pass the guidance and code to the Senior Documentation Writer.

### Senior Documentation Writer

- Update the `README.md` and other documentation files to reflect new features or changes.
- Ensure documentation is clear, concise, and easy to understand.
- Provide examples and usage instructions for new features.
- Make sure every public method is mentioned in the documentation.
- In the main `README.md`, provide a list of all public methods (grouped by class), with:
  - first column: method name (with a link to the detailed documentation, if exists)
  - second column: signature  
  - third column: short description
- When a single public method needs more explanation, create a file in `Docs/<Class>/<Method-name>.md` with detailed information and examples.
- Pass the updated documentation back to the Senior Project and Code Designer for review and approval.

### Senior Code Reviewer
- Review new/changed code for correctness, adherence to design principles, and best practices.
- Review new/changed code that it's according to the specifications (can be in readme.md, other design documents, or passed in a prompt at the very beginning of this whole process).
- Review documentation updates for clarity and completeness.
- Everything you review, must also be according to the instructions given to the relevant agent.
- Provide feedback and suggestions for improvements if necessary.
- Approve the code if it meets all requirements and standards.