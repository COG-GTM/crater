import { test } from 'node:test'
import assert from 'node:assert/strict'
import { pageToFetch } from '../../resources/scripts/components/base/base-table/helpers.js'

test('fetches the first page before any pagination exists', () => {
  assert.equal(pageToFetch(null), 1)
  assert.equal(pageToFetch(''), 1)
})

test('keeps the current page on a plain refresh', () => {
  assert.equal(pageToFetch({ currentPage: 2, totalPages: 40 }), 2)
})

test('goes back to page 1 when a new search resets the page', () => {
  assert.equal(
    pageToFetch({ currentPage: 2, totalPages: 40 }, { resetPage: true }),
    1
  )
})
