# Contributing to Sterk GraphQL Performance Module

We love your input! We want to make contributing to this module as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## Development Process
We use GitHub to host code, to track issues and feature requests, as well as accept pull requests.

1. Fork the repo and create your branch from `master`
2. If you've added code that should be tested, add tests
3. If you've changed APIs, update the documentation
4. Ensure the test suite passes
5. Make sure your code lints
6. Issue that pull request!

## Pull Request Process

1. Update the README.md with details of changes to the interface
2. Update the CHANGELOG.md with a note describing your changes
3. The PR will be merged once you have the sign-off of two other developers

## Any contributions you make will be under the MIT Software License
In short, when you submit code changes, your submissions are understood to be under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using GitHub's [issue tracker]
We use GitHub issues to track public bugs. Report a bug by [opening a new issue]().

## Write bug reports with detail, background, and sample code

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

## Coding Standards

1. Use PSR-12 coding style
2. Write tests for new features
3. Document all methods and classes
4. Follow SOLID principles
5. Keep methods small and focused
6. Use meaningful variable names

## Testing

1. Unit Tests
```bash
bin/magento dev:tests:run unit
```

2. Integration Tests
```bash
bin/magento dev:tests:run integration
```

3. Static Tests
```bash
bin/magento dev:tests:run static
```

## Documentation

1. Update relevant documentation files:
   - README.md for general documentation
   - CHANGELOG.md for version changes
   - API documentation in code
   - Example files in /docs

2. Document all public methods with PHPDoc blocks

3. Include examples for complex features

## License
By contributing, you agree that your contributions will be licensed under its MIT License.
