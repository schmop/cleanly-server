import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'node',
        include: ['*.test.ts'],
        // Single file at a time keeps RSS predictable on the 2 GB CI runner.
        fileParallelism: false,
    },
});
